import { eq } from "drizzle-orm";
import { afterEach, expect, test } from "vitest";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { hotel, room } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { updateHotelListingCore } from "./submit-listing";

const testEmails: string[] = [];

afterEach(async () => {
	await deleteTestUsers(testEmails.splice(0));
});

async function insertRejectedHotel(ownerId: string) {
	const [row] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "Old Name",
			address: "Old Address",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "rejected",
			moderationReason: "Missing photos",
		})
		.returning();
	await db.insert(room).values({
		hotelId: row.id,
		name: "Old Room",
		guestCapacity: 1,
		basePrice: "50.00",
	});
	return row;
}

function updateInput(
	overrides: Partial<Parameters<typeof updateHotelListingCore>[2]> = {},
) {
	return {
		name: "New Name",
		accommodationType: "hotel" as const,
		address: "New Address",
		latitude: 2,
		longitude: 2,
		phone: "+20000000000",
		rooms: [{ name: "New Room", guestCapacity: 3, basePrice: 120 }],
		...overrides,
	};
}

test("rejects an unauthenticated caller", async () => {
	const result = await updateHotelListingCore(
		new Headers(),
		"00000000-0000-0000-0000-000000000000",
		updateInput(),
	);
	expect(result).toEqual({ success: false, error: "UNAUTHENTICATED" });
});

test("returns NOT_FOUND for a non-existent hotel id", async () => {
	const email = "test-update-notfound@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await updateHotelListingCore(
		requestHeaders,
		"00000000-0000-0000-0000-000000000000",
		updateInput(),
	);
	expect(result).toEqual({ success: false, error: "NOT_FOUND" });
});

test("returns FORBIDDEN when the caller does not own the hotel", async () => {
	const ownerEmail = "test-update-owner@example.com";
	const otherEmail = "test-update-other@example.com";
	testEmails.push(ownerEmail, otherEmail);
	const ownerHeaders = await signUpAndGetCookieHeaders(ownerEmail);
	const otherHeaders = await signUpAndGetCookieHeaders(otherEmail);
	const owner = await getCurrentUser(ownerHeaders);
	const listing = await insertRejectedHotel(owner?.id as string);

	const result = await updateHotelListingCore(
		otherHeaders,
		listing.id,
		updateInput(),
	);
	expect(result).toEqual({ success: false, error: "FORBIDDEN" });
});

test("returns NOT_EDITABLE for a hotel that is not rejected", async () => {
	const email = "test-update-pending@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	const owner = await getCurrentUser(requestHeaders);
	const [pendingHotel] = await db
		.insert(hotel)
		.values({
			ownerId: owner?.id as string,
			name: "Pending Hotel",
			address: "1 Test St",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "pending",
		})
		.returning();

	const result = await updateHotelListingCore(
		requestHeaders,
		pendingHotel.id,
		updateInput(),
	);
	expect(result).toEqual({ success: false, error: "NOT_EDITABLE" });
});

test("a rejected hotel's owner can resubmit — status flips to pending, reason clears, fields and rooms are replaced", async () => {
	const email = "test-update-success@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	const owner = await getCurrentUser(requestHeaders);
	const listing = await insertRejectedHotel(owner?.id as string);

	const result = await updateHotelListingCore(
		requestHeaders,
		listing.id,
		updateInput(),
	);
	expect(result).toEqual({ success: true, hotelId: listing.id });

	const [updated] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, listing.id));
	expect(updated.status).toBe("pending");
	expect(updated.moderationReason).toBeNull();
	expect(updated.name).toBe("New Name");

	const rooms = await db
		.select()
		.from(room)
		.where(eq(room.hotelId, listing.id));
	expect(rooms).toHaveLength(1);
	expect(rooms[0].name).toBe("New Room");
});
