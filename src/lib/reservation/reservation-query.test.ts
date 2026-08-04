import { eq } from "drizzle-orm";
import { afterAll, beforeAll, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import {
	amenity,
	hotel,
	reservation,
	room,
	roomAmenity,
	roomMedia,
	user,
} from "@/lib/db/schema";
import {
	getGuestReservations,
	getRoomDetail,
	isRoomAvailable,
} from "./reservation-query";

const testHotelIds: string[] = [];
const testRoomIds: string[] = [];
const testReservationIds: string[] = [];
let ownerId: string;
let guestId: string;
let otherGuestId: string;
let testAmenityId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-t6a01-owner",
			name: "T6A01 Owner",
			email: "t6a01-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T6A01 Owner" } })
		.returning();
	ownerId = owner.id;

	const [guest] = await db
		.insert(user)
		.values({
			id: "test-t6a01-guest",
			name: "T6A01 Guest",
			email: "t6a01-guest@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T6A01 Guest" } })
		.returning();
	guestId = guest.id;

	const [otherGuest] = await db
		.insert(user)
		.values({
			id: "test-t6a01-other-guest",
			name: "T6A01 Other Guest",
			email: "t6a01-other-guest@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T6A01 Other Guest" } })
		.returning();
	otherGuestId = otherGuest.id;

	const [amenityRow] = await db
		.select({ id: amenity.id })
		.from(amenity)
		.limit(1);
	testAmenityId = amenityRow.id;
});

afterAll(async () => {
	for (const id of testReservationIds) {
		await db.delete(reservation).where(eq(reservation.id, id));
	}
	for (const id of testHotelIds) {
		await db.delete(hotel).where(eq(hotel.id, id));
	}
	await db.delete(user).where(eq(user.id, ownerId));
	await db.delete(user).where(eq(user.id, guestId));
	await db.delete(user).where(eq(user.id, otherGuestId));
});

test("getRoomDetail returns full detail for a published room in a published hotel", async () => {
	const [publishedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T6A01 Hotel",
			address: "1 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	testHotelIds.push(publishedHotel.id);

	const [publishedRoom] = await db
		.insert(room)
		.values({
			hotelId: publishedHotel.id,
			name: "Deluxe Suite",
			bedConfiguration: "1 king bed",
			guestCapacity: 2,
			basePrice: "200.00",
			featureTags: ["sauna", "breakfast"],
			status: "published",
		})
		.returning();
	testRoomIds.push(publishedRoom.id);

	await db.insert(roomMedia).values({
		roomId: publishedRoom.id,
		url: "https://example.test/room.jpg",
		sortOrder: 0,
	});
	await db
		.insert(roomAmenity)
		.values({ roomId: publishedRoom.id, amenityId: testAmenityId });

	const detail = await getRoomDetail(publishedRoom.id);
	expect(detail).toMatchObject({
		name: "Deluxe Suite",
		bedConfiguration: "1 king bed",
		guestCapacity: 2,
		basePrice: 200,
		featureTags: ["sauna", "breakfast"],
		photos: ["https://example.test/room.jpg"],
	});
	expect(detail?.amenities.map((a) => a.id)).toContain(testAmenityId);
});

test("getRoomDetail is undefined for a room whose hotel isn't published, even if the room itself is", async () => {
	const [pendingHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T6A01 Pending Hotel",
			address: "2 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "pending",
		})
		.returning();
	testHotelIds.push(pendingHotel.id);

	const [publishedRoomInPendingHotel] = await db
		.insert(room)
		.values({
			hotelId: pendingHotel.id,
			name: "Orphaned Room",
			guestCapacity: 2,
			basePrice: "100.00",
			status: "published",
		})
		.returning();
	testRoomIds.push(publishedRoomInPendingHotel.id);

	expect(await getRoomDetail(publishedRoomInPendingHotel.id)).toBeUndefined();
	expect(
		await getRoomDetail("00000000-0000-0000-0000-000000000000"),
	).toBeUndefined();
});

test("isRoomAvailable is blocked only by an overlapping paid reservation, not a pending one", async () => {
	const [publishedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T6A01 Availability Hotel",
			address: "3 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	testHotelIds.push(publishedHotel.id);

	const [availabilityRoom] = await db
		.insert(room)
		.values({
			hotelId: publishedHotel.id,
			name: "Availability Room",
			guestCapacity: 2,
			basePrice: "100.00",
			status: "published",
		})
		.returning();
	testRoomIds.push(availabilityRoom.id);

	const [pendingReservation] = await db
		.insert(reservation)
		.values({
			roomId: availabilityRoom.id,
			guestId,
			checkIn: "2026-09-01",
			checkOut: "2026-09-05",
			guestCount: 2,
			status: "pending",
		})
		.returning();
	testReservationIds.push(pendingReservation.id);

	// A pending reservation over these exact dates does not block them.
	expect(
		await isRoomAvailable(availabilityRoom.id, "2026-09-01", "2026-09-05"),
	).toBe(true);

	const [paidReservation] = await db
		.insert(reservation)
		.values({
			roomId: availabilityRoom.id,
			guestId,
			checkIn: "2026-10-01",
			checkOut: "2026-10-05",
			guestCount: 2,
			status: "paid",
		})
		.returning();
	testReservationIds.push(paidReservation.id);

	// Overlapping the paid range is blocked.
	expect(
		await isRoomAvailable(availabilityRoom.id, "2026-10-03", "2026-10-07"),
	).toBe(false);
	// A checkout on the paid reservation's own check-in day doesn't overlap
	// (exclusive checkout semantics).
	expect(
		await isRoomAvailable(availabilityRoom.id, "2026-09-28", "2026-10-01"),
	).toBe(true);
	// Entirely outside the paid range is available.
	expect(
		await isRoomAvailable(availabilityRoom.id, "2026-11-01", "2026-11-05"),
	).toBe(true);
});

test("getGuestReservations returns only that guest's own rows, newest first", async () => {
	const [publishedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T6A01 Guest List Hotel",
			address: "4 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	testHotelIds.push(publishedHotel.id);

	const [listRoom] = await db
		.insert(room)
		.values({
			hotelId: publishedHotel.id,
			name: "List Room",
			guestCapacity: 2,
			basePrice: "100.00",
			status: "published",
		})
		.returning();
	testRoomIds.push(listRoom.id);

	const [olderReservation] = await db
		.insert(reservation)
		.values({
			roomId: listRoom.id,
			guestId,
			checkIn: "2026-12-01",
			checkOut: "2026-12-05",
			guestCount: 2,
			status: "paid",
			createdAt: new Date("2026-01-01T00:00:00Z"),
		})
		.returning();
	testReservationIds.push(olderReservation.id);

	const [newerReservation] = await db
		.insert(reservation)
		.values({
			roomId: listRoom.id,
			guestId,
			checkIn: "2026-12-10",
			checkOut: "2026-12-12",
			guestCount: 1,
			status: "payment_failed",
			createdAt: new Date("2026-02-01T00:00:00Z"),
		})
		.returning();
	testReservationIds.push(newerReservation.id);

	const [otherGuestReservation] = await db
		.insert(reservation)
		.values({
			roomId: listRoom.id,
			guestId: otherGuestId,
			checkIn: "2026-12-15",
			checkOut: "2026-12-18",
			guestCount: 1,
			status: "paid",
		})
		.returning();
	testReservationIds.push(otherGuestReservation.id);

	const results = await getGuestReservations(guestId);
	// Scoped to this test's own two rows rather than the full result set —
	// `guestId` is shared across every test in this file (a single fixed
	// fixture user), so an earlier test's own reservations for the same guest
	// (e.g. isRoomAvailable's, only cleaned up in the file-level afterAll, not
	// per test) can legitimately still be present here too. Newest-first
	// ordering only needs to hold among this test's own rows to be proven.
	const ownResults = results.filter(
		(r) => r.id === newerReservation.id || r.id === olderReservation.id,
	);
	expect(ownResults.map((r) => r.id)).toEqual([
		newerReservation.id,
		olderReservation.id,
	]);
	expect(results.some((r) => r.id === otherGuestReservation.id)).toBe(false);
	expect(
		ownResults.every((r) => r.hotelName === "T6A01 Guest List Hotel"),
	).toBe(true);
});
