import { eq } from "drizzle-orm";
import { afterAll, afterEach, beforeAll, expect, test } from "vitest";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { hotel, reservation, room, user } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { createReservationCore } from "./create-reservation";

const testEmails: string[] = [];
let ownerId: string;
let hotelId: string;
let roomId: string;
let unpublishedRoomId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-t6b02-owner",
			name: "T6B02 Owner",
			email: "t6b02-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T6B02 Owner" } })
		.returning();
	ownerId = owner.id;

	const [publishedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T6B02 Hotel",
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
			name: "T6B02 Room",
			guestCapacity: 2,
			basePrice: "100.00",
			status: "published",
		})
		.returning();
	roomId = publishedRoom.id;

	const [pendingRoom] = await db
		.insert(room)
		.values({
			hotelId,
			name: "T6B02 Unpublished Room",
			guestCapacity: 2,
			basePrice: "100.00",
			status: "pending",
		})
		.returning();
	unpublishedRoomId = pendingRoom.id;
});

afterEach(async () => {
	// Removes any reservations the just-finished test created (by guestId)
	// before the fixture room/hotel are torn down in afterAll — reservation
	// has no onDelete cascade from room, so order matters here.
	await deleteTestUsers(testEmails.splice(0));
});

afterAll(async () => {
	await db.delete(hotel).where(eq(hotel.id, hotelId));
	await db.delete(user).where(eq(user.id, ownerId));
});

test("rejects an unauthenticated caller", async () => {
	const result = await createReservationCore(new Headers(), {
		roomId,
		checkIn: "2026-09-01",
		checkOut: "2026-09-05",
		guestCount: 1,
	});
	expect(result).toEqual({ success: false, error: "UNAUTHENTICATED" });
});

test("rejects invalid input (checkOut before checkIn) without touching the database", async () => {
	const email = "test-reserve-invalid@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-09-05",
		checkOut: "2026-09-01",
		guestCount: 1,
	});
	expect(result.success).toBe(false);
	if (!result.success) {
		expect(result.error).toBe("VALIDATION_ERROR");
	}
});

test("rejects a room that isn't published", async () => {
	const email = "test-reserve-unpublished@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await createReservationCore(requestHeaders, {
		roomId: unpublishedRoomId,
		checkIn: "2026-09-10",
		checkOut: "2026-09-12",
		guestCount: 1,
	});
	expect(result).toEqual({ success: false, error: "ROOM_NOT_FOUND" });
});

test("rejects a guest count over the room's capacity", async () => {
	const email = "test-reserve-capacity@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-09-15",
		checkOut: "2026-09-17",
		guestCount: 3,
	});
	expect(result).toEqual({ success: false, error: "CAPACITY_EXCEEDED" });
});

test("rejects dates overlapping an existing paid reservation", async () => {
	const blockerEmail = "test-reserve-blocker@example.com";
	const seekerEmail = "test-reserve-seeker@example.com";
	testEmails.push(blockerEmail, seekerEmail);

	const blockerHeaders = await signUpAndGetCookieHeaders(blockerEmail);
	const blocked = await createReservationCore(blockerHeaders, {
		roomId,
		checkIn: "2026-10-01",
		checkOut: "2026-10-05",
		guestCount: 1,
	});
	expect(blocked.success).toBe(true);
	if (!blocked.success) throw new Error("expected success");
	await db
		.update(reservation)
		.set({ status: "paid" })
		.where(eq(reservation.id, blocked.reservationId));

	const seekerHeaders = await signUpAndGetCookieHeaders(seekerEmail);
	const result = await createReservationCore(seekerHeaders, {
		roomId,
		checkIn: "2026-10-03",
		checkOut: "2026-10-07",
		guestCount: 1,
	});
	expect(result).toEqual({ success: false, error: "UNAVAILABLE" });
});

test("creates a pending reservation for a valid, available room", async () => {
	const email = "test-reserve-success@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	const guest = await getCurrentUser(requestHeaders);

	const result = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-11-01",
		checkOut: "2026-11-05",
		guestCount: 2,
	});
	expect(result.success).toBe(true);
	if (!result.success) throw new Error("expected success");

	const [inserted] = await db
		.select()
		.from(reservation)
		.where(eq(reservation.id, result.reservationId));
	expect(inserted).toMatchObject({
		roomId,
		guestId: guest?.id,
		checkIn: "2026-11-01",
		checkOut: "2026-11-05",
		guestCount: 2,
		status: "pending",
	});
});
