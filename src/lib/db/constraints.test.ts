import { eq } from "drizzle-orm";
import { expect, test } from "vitest";
import { db } from "./client";
import { hotel, review, room, user } from "./schema";

test("room requires a hotel_id — a null reference is rejected", async () => {
	let caught: unknown;
	try {
		await db.insert(room).values({
			// @ts-expect-error hotelId is intentionally omitted to prove the DB rejects it
			hotelId: null,
			name: "Test room",
			guestCapacity: 2,
			basePrice: "100.00",
		});
	} catch (error) {
		caught = error;
	}

	expect(caught).toBeDefined();
	const pgError = (caught as { cause?: { code?: string } }).cause;
	// 23502 = not_null_violation (Postgres error code)
	expect(pgError?.code).toBe("23502");
});

test("hotel, room, and review default status to pending", async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-owner-1",
			name: "Test Owner",
			email: "owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "Test Owner" } })
		.returning();

	const [insertedHotel] = await db
		.insert(hotel)
		.values({
			ownerId: owner.id,
			name: "Test Hotel",
			address: "1 Test St",
			latitude: 0,
			longitude: 0,
			phone: "+10000000000",
		})
		.returning();

	expect(insertedHotel.status).toBe("pending");
	// A submitted listing is attributable to its owner via hotel.owner_id
	// (l1-platform-foundation.md §3 — T-2T01 invariant).
	expect(insertedHotel.ownerId).toBe(owner.id);

	const [insertedRoom] = await db
		.insert(room)
		.values({
			hotelId: insertedHotel.id,
			name: "Test room",
			guestCapacity: 2,
			basePrice: "100.00",
		})
		.returning();

	expect(insertedRoom.status).toBe("pending");

	const [insertedReview] = await db
		.insert(review)
		.values({
			hotelId: insertedHotel.id,
			guestId: owner.id,
			rating: 5,
			comment: "Test review",
		})
		.returning();

	expect(insertedReview.status).toBe("pending");

	await db.delete(review).where(eq(review.id, insertedReview.id));
	await db.delete(room).where(eq(room.id, insertedRoom.id));
	await db.delete(hotel).where(eq(hotel.id, insertedHotel.id));
	await db.delete(user).where(eq(user.id, owner.id));
});
