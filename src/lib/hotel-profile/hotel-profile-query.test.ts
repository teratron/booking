import { eq } from "drizzle-orm";
import { afterAll, beforeAll, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import {
	amenity,
	hotel,
	hotelAmenity,
	hotelMedia,
	review,
	room,
	roomMedia,
	user,
} from "@/lib/db/schema";
import { getHotelProfile } from "./hotel-profile-query";

const testHotelIds: string[] = [];
let ownerId: string;
let guestId: string;
let testAmenityId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-t5a02-owner",
			name: "T5A02 Owner",
			email: "t5a02-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T5A02 Owner" } })
		.returning();
	ownerId = owner.id;

	const [guest] = await db
		.insert(user)
		.values({
			id: "test-t5a02-guest",
			name: "T5A02 Guest",
			email: "t5a02-guest@test.local",
			image: "https://example.test/guest.jpg",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T5A02 Guest" } })
		.returning();
	guestId = guest.id;

	const [amenityRow] = await db
		.select({ id: amenity.id })
		.from(amenity)
		.limit(1);
	testAmenityId = amenityRow.id;
});

afterAll(async () => {
	for (const id of testHotelIds) {
		await db.delete(hotel).where(eq(hotel.id, id));
	}
	await db.delete(user).where(eq(user.id, ownerId));
	await db.delete(user).where(eq(user.id, guestId));
});

test("returns the full aggregation for a published hotel with a complete fixture", async () => {
	const [fullHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5A02 Full Hotel",
			address: "1 Fixture Ave",
			latitude: 10,
			longitude: 20,
			phone: "+10000000000",
			starCategory: 5,
			status: "published",
		})
		.returning();
	testHotelIds.push(fullHotel.id);

	await db.insert(hotelMedia).values({
		hotelId: fullHotel.id,
		url: "https://example.test/gallery-1.jpg",
		type: "photo",
		sortOrder: 0,
	});
	await db
		.insert(hotelAmenity)
		.values({ hotelId: fullHotel.id, amenityId: testAmenityId });

	const [publishedRoom] = await db
		.insert(room)
		.values({
			hotelId: fullHotel.id,
			name: "Suite",
			guestCapacity: 2,
			basePrice: "150.00",
			status: "published",
		})
		.returning();
	await db.insert(roomMedia).values({
		roomId: publishedRoom.id,
		url: "https://example.test/room-1.jpg",
		sortOrder: 0,
	});

	await db.insert(review).values({
		hotelId: fullHotel.id,
		guestId,
		rating: 4,
		comment: "Lovely stay",
		status: "published",
	});

	const profile = await getHotelProfile(fullHotel.id);
	expect(profile).toBeDefined();
	expect(profile?.name).toBe("T5A02 Full Hotel");
	expect(profile?.galleryPhotos).toEqual([
		"https://example.test/gallery-1.jpg",
	]);
	expect(profile?.amenities.map((a) => a.id)).toContain(testAmenityId);
	expect(profile?.rooms).toHaveLength(1);
	expect(profile?.rooms[0]).toMatchObject({
		name: "Suite",
		guestCapacity: 2,
		basePrice: 150,
		coverPhotoUrl: "https://example.test/room-1.jpg",
	});
	expect(profile?.avgRating).toBe(4);
	expect(profile?.reviewCount).toBe(1);
	expect(profile?.reviews[0]).toMatchObject({
		guestName: "T5A02 Guest",
		guestAvatar: "https://example.test/guest.jpg",
		rating: 4,
		comment: "Lovely stay",
	});
});

test("returns a well-formed empty-array shape for a hotel with zero media/amenities/rooms/reviews", async () => {
	const [minimalHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5A02 Minimal Hotel",
			address: "2 Fixture Ave",
			latitude: 10,
			longitude: 20,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	testHotelIds.push(minimalHotel.id);

	const profile = await getHotelProfile(minimalHotel.id);
	expect(profile).toBeDefined();
	expect(profile?.galleryPhotos).toEqual([]);
	expect(profile?.amenities).toEqual([]);
	expect(profile?.rooms).toEqual([]);
	expect(profile?.reviews).toEqual([]);
	expect(profile?.avgRating).toBeNull();
	expect(profile?.reviewCount).toBe(0);
});

test("the moderation checkpoint applies to a single-hotel lookup: pending, rejected, and missing ids all return undefined", async () => {
	const [pendingHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5A02 Pending Hotel",
			address: "3 Fixture Ave",
			latitude: 10,
			longitude: 20,
			phone: "+10000000000",
			status: "pending",
		})
		.returning();
	const [rejectedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5A02 Rejected Hotel",
			address: "4 Fixture Ave",
			latitude: 10,
			longitude: 20,
			phone: "+10000000000",
			status: "rejected",
		})
		.returning();
	testHotelIds.push(pendingHotel.id, rejectedHotel.id);

	expect(await getHotelProfile(pendingHotel.id)).toBeUndefined();
	expect(await getHotelProfile(rejectedHotel.id)).toBeUndefined();
	expect(
		await getHotelProfile("00000000-0000-0000-0000-000000000000"),
	).toBeUndefined();
});
