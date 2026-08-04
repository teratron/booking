import { eq } from "drizzle-orm";
import { afterAll, afterEach, beforeAll, expect, test } from "vitest";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { hotel, room, user } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { resolvePaymentCore } from "./checkout";
import { createReservationCore } from "./create-reservation";
import { getGuestReservations, isRoomAvailable } from "./reservation-query";

// Phase exit gate (T-6T01) — mirrors T-4T01/T-5T01's role: prove the complete
// guest journey holds end-to-end across every module this phase built, not
// just each module in isolation. Each module's own edge cases are already
// covered by its own test file (reservation-query.test.ts,
// create-reservation.test.ts, checkout.test.ts) — this file only proves the
// hand-offs between them are correct.

const testEmails: string[] = [];
let ownerId: string;
let hotelId: string;
let roomId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-t6t01-owner",
			name: "T6T01 Owner",
			email: "t6t01-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T6T01 Owner" } })
		.returning();
	ownerId = owner.id;

	const [publishedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T6T01 Hotel",
			address: "1 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	hotelId = publishedHotel.id;

	const [publishedRoom] = await db
		.insert(room)
		.values({
			hotelId,
			name: "T6T01 Room",
			guestCapacity: 2,
			basePrice: "100.00",
			status: "published",
		})
		.returning();
	roomId = publishedRoom.id;
});

afterEach(async () => {
	await deleteTestUsers(testEmails.splice(0));
});

afterAll(async () => {
	await db.delete(hotel).where(eq(hotel.id, hotelId));
	await db.delete(user).where(eq(user.id, ownerId));
});

test("the full guest journey: reserve -> pending (doesn't block) -> pay -> paid (blocks + visible in the guest's own list) -> a second guest is rejected for the same dates", async () => {
	const guestEmail = "test-t6t01-journey-guest@example.com";
	testEmails.push(guestEmail);
	const guestHeaders = await signUpAndGetCookieHeaders(guestEmail);
	const guest = await getCurrentUser(guestHeaders);
	if (!guest) throw new Error("expected a signed-in guest");

	// 1. The room is available before anyone touches these dates.
	expect(await isRoomAvailable(roomId, "2027-01-10", "2027-01-15")).toBe(true);

	// 2. Reserve -> a `pending` row.
	const created = await createReservationCore(guestHeaders, {
		roomId,
		checkIn: "2027-01-10",
		checkOut: "2027-01-15",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	// 3. Per T-6A01's own [DR]: an unpaid `pending` reservation does not hold
	// the dates against availability — a second guest can still see them open.
	expect(await isRoomAvailable(roomId, "2027-01-10", "2027-01-15")).toBe(true);

	// 4. Pay -> `paid`.
	const resolved = await resolvePaymentCore(
		guestHeaders,
		created.reservationId,
		"success",
	);
	expect(resolved).toEqual({ success: true, status: "paid" });

	// 5. Now a `paid` reservation genuinely blocks the dates.
	expect(await isRoomAvailable(roomId, "2027-01-10", "2027-01-15")).toBe(false);

	// 6. The outcome is visible from the guest's own account page's data source.
	const guestReservations = await getGuestReservations(guest.id);
	const ownRow = guestReservations.find((r) => r.id === created.reservationId);
	expect(ownRow?.status).toBe("paid");
	expect(ownRow?.hotelName).toBe("T6T01 Hotel");

	// 7. A second guest attempting the same (or an overlapping) range is
	// rejected at reservation-creation time, not just at the availability
	// query level — proving the two layers agree.
	const secondEmail = "test-t6t01-journey-second-guest@example.com";
	testEmails.push(secondEmail);
	const secondHeaders = await signUpAndGetCookieHeaders(secondEmail);
	const secondAttempt = await createReservationCore(secondHeaders, {
		roomId,
		checkIn: "2027-01-12",
		checkOut: "2027-01-14",
		guestCount: 1,
	});
	expect(secondAttempt).toEqual({ success: false, error: "UNAVAILABLE" });
});

test("a failed payment leaves the reservation payment_failed and the dates available to a different guest", async () => {
	const guestEmail = "test-t6t01-failure-guest@example.com";
	testEmails.push(guestEmail);
	const guestHeaders = await signUpAndGetCookieHeaders(guestEmail);

	const created = await createReservationCore(guestHeaders, {
		roomId,
		checkIn: "2027-02-01",
		checkOut: "2027-02-05",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	const resolved = await resolvePaymentCore(
		guestHeaders,
		created.reservationId,
		"failure",
	);
	expect(resolved).toEqual({ success: true, status: "payment_failed" });

	// No explicit "release" step needed — payment_failed was never `paid`, so
	// it never held the dates in the first place (T-6A01's own [DR] falls out
	// for free here).
	expect(await isRoomAvailable(roomId, "2027-02-01", "2027-02-05")).toBe(true);

	// A different guest can freely reserve the same dates.
	const otherEmail = "test-t6t01-failure-other-guest@example.com";
	testEmails.push(otherEmail);
	const otherHeaders = await signUpAndGetCookieHeaders(otherEmail);
	const otherAttempt = await createReservationCore(otherHeaders, {
		roomId,
		checkIn: "2027-02-01",
		checkOut: "2027-02-05",
		guestCount: 1,
	});
	expect(otherAttempt.success).toBe(true);
});
