import { randomUUID } from "node:crypto";
import { eq } from "drizzle-orm";
import { afterEach, expect, test } from "vitest";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import {
	amenity,
	hotel,
	hotelAmenity,
	hotelMedia,
	room,
	roomAmenity,
	roomMedia,
} from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { submitHotelListingCore } from "./submit-listing";

const testEmails: string[] = [];

afterEach(async () => {
	await deleteTestUsers(testEmails.splice(0));
});

function baseInput(
	overrides: Partial<Parameters<typeof submitHotelListingCore>[1]> = {},
) {
	return {
		name: "Test Hotel",
		accommodationType: "hotel" as const,
		address: "1 Test St",
		latitude: 50.45,
		longitude: 30.52,
		phone: "+380000000000",
		rooms: [
			{
				name: "Standard Room",
				guestCapacity: 2,
				basePrice: 100,
			},
		],
		...overrides,
	};
}

test("rejects an unauthenticated caller", async () => {
	const result = await submitHotelListingCore(new Headers(), baseInput());
	expect(result).toEqual({ success: false, error: "UNAUTHENTICATED" });
});

test("rejects invalid input without touching the database", async () => {
	const email = "test-submit-invalid@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await submitHotelListingCore(requestHeaders, {
		...baseInput(),
		// biome-ignore lint/suspicious/noExplicitAny: deliberately invalid input
		name: "" as any,
	});

	expect(result.success).toBe(false);
	if (!result.success) {
		expect(result.error).toBe("VALIDATION_ERROR");
	}
});

test("a guest submission creates a pending hotel attributed to the caller, and promotes the caller to owner", async () => {
	const email = "test-submit-guest@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	const before = await getCurrentUser(requestHeaders);
	expect(before?.role).toBe("guest");

	const result = await submitHotelListingCore(requestHeaders, baseInput());
	expect(result.success).toBe(true);
	if (!result.success) throw new Error("expected success");

	const [insertedHotel] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, result.hotelId));
	expect(insertedHotel.status).toBe("pending");
	expect(insertedHotel.ownerId).toBe(before?.id);

	const after = await getCurrentUser(requestHeaders);
	expect(after?.role).toBe("owner");
});

test("persists rooms, amenity links, and media for the same hotel in one transaction", async () => {
	const email = "test-submit-full@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const [hotelAmenityRow, roomAmenityRow] = await db
		.select({ id: amenity.id })
		.from(amenity)
		.limit(2);

	const result = await submitHotelListingCore(
		requestHeaders,
		baseInput({
			amenityIds: [hotelAmenityRow.id],
			media: [{ url: "https://example.com/photo.jpg", type: "photo" }],
			rooms: [
				{
					name: "Deluxe Room",
					guestCapacity: 3,
					basePrice: 150,
					amenityIds: [roomAmenityRow.id],
					mediaUrls: ["https://example.com/room.jpg"],
				},
			],
		}),
	);
	expect(result.success).toBe(true);
	if (!result.success) throw new Error("expected success");

	const rooms = await db
		.select()
		.from(room)
		.where(eq(room.hotelId, result.hotelId));
	expect(rooms).toHaveLength(1);

	const hotelAmenityLinks = await db
		.select()
		.from(hotelAmenity)
		.where(eq(hotelAmenity.hotelId, result.hotelId));
	expect(hotelAmenityLinks).toEqual([
		{ hotelId: result.hotelId, amenityId: hotelAmenityRow.id },
	]);

	const hotelMediaRows = await db
		.select()
		.from(hotelMedia)
		.where(eq(hotelMedia.hotelId, result.hotelId));
	expect(hotelMediaRows).toHaveLength(1);
	expect(hotelMediaRows[0].url).toBe("https://example.com/photo.jpg");

	const roomAmenityLinks = await db
		.select()
		.from(roomAmenity)
		.where(eq(roomAmenity.roomId, rooms[0].id));
	expect(roomAmenityLinks).toEqual([
		{ roomId: rooms[0].id, amenityId: roomAmenityRow.id },
	]);

	const roomMediaRows = await db
		.select()
		.from(roomMedia)
		.where(eq(roomMedia.roomId, rooms[0].id));
	expect(roomMediaRows).toHaveLength(1);
	expect(roomMediaRows[0].url).toBe("https://example.com/room.jpg");
});

test("a forced failure mid-transaction rolls back the hotel insert too", async () => {
	const email = "test-submit-rollback@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	const caller = await getCurrentUser(requestHeaders);

	// A syntactically valid but non-existent amenity id passes zod's .uuid()
	// check but violates room_amenity's FK — Postgres rejects the statement
	// and drizzle's db.transaction() rethrows, it does not return a graceful
	// result. That rethrow is exactly what proves the transaction is atomic.
	await expect(
		submitHotelListingCore(
			requestHeaders,
			baseInput({
				name: "Should Not Persist",
				rooms: [
					{
						name: "Broken Room",
						guestCapacity: 2,
						basePrice: 100,
						amenityIds: [randomUUID()],
					},
				],
			}),
		),
	).rejects.toThrow();

	const survivors = await db
		.select()
		.from(hotel)
		.where(eq(hotel.ownerId, caller?.id as string));
	expect(survivors).toHaveLength(0);
});
