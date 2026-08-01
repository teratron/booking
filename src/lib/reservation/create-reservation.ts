import { and, eq } from "drizzle-orm";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { hotel, reservation, room } from "@/lib/db/schema";
import { isRoomAvailable } from "./reservation-query";
import {
	type CreateReservationResult,
	type ReservationInput,
	reservationInputSchema,
} from "./schema";

/**
 * Framework-agnostic core, mirroring lib/property-onboarding/submit-listing.ts's
 * pattern: takes an explicit Headers object so it is directly unit-testable.
 * The "use server" wrapper lives in actions.ts.
 *
 * Availability is re-checked here even though the dialog already checked it
 * client-side — closes the window between browsing dates and clicking
 * Reserve. This is a courtesy check, not the authoritative concurrency guard:
 * per l1-room-reservation.md's "unpaid attempts don't hold dates" invariant,
 * two guests can both hold a `pending` reservation for overlapping dates —
 * the payment step (T-6C01) is what must re-check availability atomically
 * before marking one of them `paid`.
 */
export async function createReservationCore(
	requestHeaders: Headers,
	input: ReservationInput,
): Promise<CreateReservationResult> {
	const currentUser = await getCurrentUser(requestHeaders);
	if (!currentUser) {
		return { success: false, error: "UNAUTHENTICATED" };
	}

	const parsed = reservationInputSchema.safeParse(input);
	if (!parsed.success) {
		return {
			success: false,
			error: "VALIDATION_ERROR",
			issues: parsed.error.issues,
		};
	}
	const data = parsed.data;

	const [roomRow] = await db
		.select({ id: room.id, guestCapacity: room.guestCapacity })
		.from(room)
		.innerJoin(hotel, eq(hotel.id, room.hotelId))
		.where(
			and(
				eq(room.id, data.roomId),
				eq(room.status, "published"),
				eq(hotel.status, "published"),
			),
		);
	if (!roomRow) {
		return { success: false, error: "ROOM_NOT_FOUND" };
	}
	if (data.guestCount > roomRow.guestCapacity) {
		return { success: false, error: "CAPACITY_EXCEEDED" };
	}

	const available = await isRoomAvailable(
		data.roomId,
		data.checkIn,
		data.checkOut,
	);
	if (!available) {
		return { success: false, error: "UNAVAILABLE" };
	}

	const [inserted] = await db
		.insert(reservation)
		.values({
			roomId: data.roomId,
			guestId: currentUser.id,
			checkIn: data.checkIn,
			checkOut: data.checkOut,
			guestCount: data.guestCount,
			status: "pending",
		})
		.returning();

	return { success: true, reservationId: inserted.id };
}
