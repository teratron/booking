import { eq } from "drizzle-orm";
import { NextRequest } from "next/server";
import { afterEach, expect, test } from "vitest";
import { POST as rejectHotel } from "@/app/api/admin/[resource]/[id]/reject/route";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { hotel, user } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { getOwnerListings } from "./queries";
import {
	submitHotelListingCore,
	updateHotelListingCore,
} from "./submit-listing";

// Phase 3 exit gate (T-3T01) — proves the full lifecycle across Phase 2 and
// Phase 3 pieces end to end, not just each piece in isolation: a guest
// submits, an admin rejects it (Phase 2), the owner edits and resubmits
// (T-3C02), and an unrelated authenticated user is proven unable to read or
// edit it (T-3C01 scoping, T-3C02 ownership gate).

const testEmails: string[] = [];

afterEach(async () => {
	await deleteTestUsers(testEmails.splice(0));
});

function rejectRequest(headers: Headers, reason: string) {
	return new NextRequest("http://localhost:3000/api/admin/hotel/x/reject", {
		method: "POST",
		headers: new Headers({
			...Object.fromEntries(headers.entries()),
			"content-type": "application/json",
		}),
		body: JSON.stringify({ reason }),
	});
}

test("submit -> admin reject -> owner edit/resubmit -> a stranger can't read or edit it", async () => {
	const ownerEmail = "test-lifecycle-owner@example.com";
	const adminEmail = "test-lifecycle-admin@example.com";
	const strangerEmail = "test-lifecycle-stranger@example.com";
	testEmails.push(ownerEmail, adminEmail, strangerEmail);

	// 1. A guest submits a listing.
	const ownerHeaders = await signUpAndGetCookieHeaders(ownerEmail);
	const submitResult = await submitHotelListingCore(ownerHeaders, {
		name: "Lifecycle Hotel",
		accommodationType: "hotel",
		address: "1 Lifecycle St",
		latitude: 1,
		longitude: 1,
		phone: "+10000000000",
		rooms: [{ name: "Standard", guestCapacity: 2, basePrice: 100 }],
	});
	expect(submitResult.success).toBe(true);
	if (!submitResult.success) throw new Error("expected success");
	const hotelId = submitResult.hotelId;

	const [pendingRow] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, hotelId));
	expect(pendingRow.status).toBe("pending");
	const promotedOwner = await getCurrentUser(ownerHeaders);
	expect(promotedOwner?.role).toBe("owner");

	// 2. An admin rejects it with a reason (Phase 2's real Route Handler).
	const adminHeaders = await signUpAndGetCookieHeaders(adminEmail, "Admin");
	await db
		.update(user)
		.set({ role: "admin" })
		.where(eq(user.email, adminEmail));
	const rejectResponse = await rejectHotel(
		rejectRequest(adminHeaders, "Missing hotel photos"),
		{ params: Promise.resolve({ resource: "hotel", id: hotelId }) },
	);
	expect(rejectResponse.status).toBe(200);

	const [rejectedRow] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, hotelId));
	expect(rejectedRow.status).toBe("rejected");
	expect(rejectedRow.moderationReason).toBe("Missing hotel photos");

	// 3. The owner edits and resubmits.
	const updateResult = await updateHotelListingCore(ownerHeaders, hotelId, {
		name: "Lifecycle Hotel (fixed)",
		accommodationType: "hotel",
		address: "1 Lifecycle St",
		latitude: 1,
		longitude: 1,
		phone: "+10000000000",
		media: [{ url: "https://example.com/lobby.jpg", type: "photo" }],
		rooms: [{ name: "Standard", guestCapacity: 2, basePrice: 100 }],
	});
	expect(updateResult).toEqual({ success: true, hotelId });

	const [resubmittedRow] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, hotelId));
	expect(resubmittedRow.status).toBe("pending");
	expect(resubmittedRow.moderationReason).toBeNull();
	expect(resubmittedRow.name).toBe("Lifecycle Hotel (fixed)");

	// 4. A stranger — authenticated, but neither the owner nor an admin —
	// cannot see it in their own dashboard listing, and cannot edit it.
	const strangerHeaders = await signUpAndGetCookieHeaders(strangerEmail);
	const stranger = await getCurrentUser(strangerHeaders);

	const strangerListings = await getOwnerListings(stranger?.id as string);
	expect(strangerListings.find((l) => l.id === hotelId)).toBeUndefined();

	const strangerEditAttempt = await updateHotelListingCore(
		strangerHeaders,
		hotelId,
		{
			name: "Hijacked",
			accommodationType: "hotel",
			address: "1 Lifecycle St",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			rooms: [{ name: "Standard", guestCapacity: 2, basePrice: 100 }],
		},
	);
	expect(strangerEditAttempt).toEqual({ success: false, error: "FORBIDDEN" });

	// The hijack attempt must not have mutated anything.
	const [finalRow] = await db.select().from(hotel).where(eq(hotel.id, hotelId));
	expect(finalRow.name).toBe("Lifecycle Hotel (fixed)");

	// The real owner still sees their listing, correctly labeled.
	const ownerListings = await getOwnerListings(promotedOwner?.id as string);
	const ownerListing = ownerListings.find((l) => l.id === hotelId);
	expect(ownerListing?.status).toBe("pending");
});
