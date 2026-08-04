import { and, eq, gt, lt, ne } from "drizzle-orm";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { reservation, room } from "@/lib/db/schema";
import { getPaymentProvider, type PaymentOutcome } from "./payment-provider";
import { calculateNightsAndAmount } from "./pricing";

export type InitiatePaymentResult =
	| { success: true; checkoutUrl: string }
	| {
			success: false;
			error: "UNAUTHENTICATED" | "NOT_FOUND" | "FORBIDDEN" | "NOT_PENDING";
	  };

export type ResolvePaymentResult =
	| { success: true; status: "paid" | "payment_failed" }
	| {
			success: false;
			error:
				| "UNAUTHENTICATED"
				| "NOT_FOUND"
				| "FORBIDDEN"
				| "NOT_PENDING"
				| "INVALID_OUTCOME";
	  };

/**
 * Framework-agnostic core, mirroring create-reservation.ts's pattern: takes
 * an explicit Headers object so it is directly unit-testable. The "use
 * server" wrapper lives in checkout-actions.ts.
 *
 * Starts a payment attempt for an already-created `pending` reservation and
 * records the provider's reference on the row. This does NOT mark the
 * reservation `paid` — initiating a payment attempt is not itself success;
 * that transition only happens once the provider reports an outcome, via
 * `resolvePaymentCore` below.
 */
export async function initiatePaymentCore(
	requestHeaders: Headers,
	reservationId: string,
): Promise<InitiatePaymentResult> {
	const currentUser = await getCurrentUser(requestHeaders);
	if (!currentUser) {
		return { success: false, error: "UNAUTHENTICATED" };
	}

	const [row] = await db
		.select({
			id: reservation.id,
			guestId: reservation.guestId,
			status: reservation.status,
			checkIn: reservation.checkIn,
			checkOut: reservation.checkOut,
			basePrice: room.basePrice,
		})
		.from(reservation)
		.innerJoin(room, eq(room.id, reservation.roomId))
		.where(eq(reservation.id, reservationId));
	if (!row) {
		return { success: false, error: "NOT_FOUND" };
	}
	if (row.guestId !== currentUser.id) {
		return { success: false, error: "FORBIDDEN" };
	}
	if (row.status !== "pending") {
		return { success: false, error: "NOT_PENDING" };
	}

	const { amount } = calculateNightsAndAmount(
		row.checkIn,
		row.checkOut,
		row.basePrice,
	);

	const attempt = await getPaymentProvider().createPayment({
		reservationId: row.id,
		amount,
		currency: "UAH",
	});

	// Overwrite any previous reference unconditionally — a simulated provider
	// has no real external resource to leak, so a "don't call twice" guard
	// would only add complexity without a corresponding real cost to guard
	// against. A real Fondy implementation would need idempotency handling
	// (see simulated-payment-provider.ts's doc comment), but that belongs in
	// the provider implementation, not here.
	await db
		.update(reservation)
		.set({ paymentReference: attempt.reference, updatedAt: new Date() })
		.where(eq(reservation.id, row.id));

	return { success: true, checkoutUrl: attempt.checkoutUrl };
}

/**
 * Framework-agnostic core for resolving a payment attempt's outcome, mirroring
 * initiatePaymentCore's shape. A real gateway reaches this indirectly, via a
 * webhook handler that verifies the callback signature and then calls this
 * function with the outcome it reported — signature verification is a
 * transport-level concern of that (not-yet-built) webhook route, not this
 * function's job.
 *
 * The in-transaction re-check below closes the concurrency window
 * create-reservation.ts's own courtesy check explicitly could not (see its
 * doc comment) — but it is a best-effort mitigation, not an airtight
 * guarantee. Postgres's default READ COMMITTED isolation does not prevent
 * two concurrent transactions from both observing "no overlap" before either
 * commits, so a true double-booking under high concurrency is still
 * possible. A fully airtight guarantee needs a DB-level exclusion constraint
 * (a `daterange` column plus an `EXCLUDE USING gist` constraint on
 * `(room_id, daterange) WHERE status = 'paid'`), which is a schema migration
 * out of this task's scope — a real, known follow-up, not a claim that the
 * current code is airtight.
 */
export async function resolvePaymentCore(
	requestHeaders: Headers,
	reservationId: string,
	outcome: PaymentOutcome,
): Promise<ResolvePaymentResult> {
	const currentUser = await getCurrentUser(requestHeaders);
	if (!currentUser) {
		return { success: false, error: "UNAUTHENTICATED" };
	}

	// Validated strictly rather than trusted at the type level: a Server
	// Action's TypeScript parameter types are not enforced at runtime, and a
	// hand-crafted request with a garbage `outcome` string must not silently
	// fall through to the success branch below.
	if (outcome !== "success" && outcome !== "failure") {
		return { success: false, error: "INVALID_OUTCOME" };
	}

	const [row] = await db
		.select({
			id: reservation.id,
			roomId: reservation.roomId,
			guestId: reservation.guestId,
			status: reservation.status,
			checkIn: reservation.checkIn,
			checkOut: reservation.checkOut,
		})
		.from(reservation)
		.where(eq(reservation.id, reservationId));
	if (!row) {
		return { success: false, error: "NOT_FOUND" };
	}
	if (row.guestId !== currentUser.id) {
		return { success: false, error: "FORBIDDEN" };
	}
	if (row.status !== "pending") {
		return { success: false, error: "NOT_PENDING" };
	}

	// The reservation's own `status = 'pending'` re-check is made atomic with
	// the terminal-status write below by folding it into the UPDATE's WHERE
	// clause instead of relying on the plain SELECT above (which is not part
	// of this transaction). Two concurrent/duplicate calls for the same
	// reservation — e.g. a retried webhook delivery, standard for
	// at-least-once webhook delivery once a real gateway is wired in — can
	// then never both "win": Postgres serializes the two UPDATEs against the
	// same row, and whichever runs second finds `status` no longer `pending`
	// and updates zero rows, which is reported back as "race" below instead
	// of silently overwriting whatever the first call already wrote.
	const status: "paid" | "payment_failed" | "race" = await db.transaction(
		async (tx): Promise<"paid" | "payment_failed" | "race"> => {
			if (outcome === "failure") {
				const updated = await tx
					.update(reservation)
					.set({ status: "payment_failed", updatedAt: new Date() })
					.where(
						and(eq(reservation.id, row.id), eq(reservation.status, "pending")),
					)
					.returning({ id: reservation.id });
				return updated.length > 0 ? "payment_failed" : "race";
			}

			// outcome === "success" — re-check for a competing `paid` reservation
			// on the same room whose dates overlap this one's, using the same
			// exclusive-checkout overlap predicate as isRoomAvailable, restricted
			// to this row's own room and excluding itself.
			const overlapping = await tx
				.select({ id: reservation.id })
				.from(reservation)
				.where(
					and(
						eq(reservation.roomId, row.roomId),
						eq(reservation.status, "paid"),
						ne(reservation.id, row.id),
						lt(reservation.checkIn, row.checkOut),
						gt(reservation.checkOut, row.checkIn),
					),
				)
				.limit(1);

			// The gateway said success, but another reservation already holds
			// these dates as `paid` — a real double-booking must never happen
			// just because this payment happened to settle second.
			const nextStatus: "paid" | "payment_failed" =
				overlapping.length > 0 ? "payment_failed" : "paid";

			const updated = await tx
				.update(reservation)
				.set({ status: nextStatus, updatedAt: new Date() })
				.where(
					and(eq(reservation.id, row.id), eq(reservation.status, "pending")),
				)
				.returning({ id: reservation.id });

			return updated.length > 0 ? nextStatus : "race";
		},
	);

	if (status === "race") {
		// Another call already resolved this reservation between our courtesy
		// SELECT above and the atomic UPDATE — treat it the same as the plain
		// "not pending anymore" case rather than overwriting the terminal
		// status the other call already wrote.
		return { success: false, error: "NOT_PENDING" };
	}

	return { success: true, status };
}
