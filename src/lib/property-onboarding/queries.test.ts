import { afterEach, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import { hotel } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { getCurrentUser } from "../auth/session";
import { getOwnerListings } from "./queries";

const testEmails: string[] = [];

afterEach(async () => {
	await deleteTestUsers(testEmails.splice(0));
});

async function insertHotel(
	ownerId: string,
	overrides: Partial<typeof hotel.$inferInsert> = {},
) {
	const [row] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "Test Hotel",
			address: "1 Test St",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			...overrides,
		})
		.returning();
	return row;
}

test("returns only hotels owned by the given caller, most recent first", async () => {
	const emailA = "test-dashboard-owner-a@example.com";
	const emailB = "test-dashboard-owner-b@example.com";
	testEmails.push(emailA, emailB);
	const headersA = await signUpAndGetCookieHeaders(emailA);
	const headersB = await signUpAndGetCookieHeaders(emailB);
	const ownerA = await getCurrentUser(headersA);
	const ownerB = await getCurrentUser(headersB);

	await insertHotel(ownerA?.id as string, { name: "Owner A Hotel" });
	await insertHotel(ownerB?.id as string, { name: "Owner B Hotel" });

	const listings = await getOwnerListings(ownerA?.id as string);
	expect(listings).toHaveLength(1);
	expect(listings[0].name).toBe("Owner A Hotel");
});

test("surfaces status and moderation reason for a rejected listing", async () => {
	const email = "test-dashboard-rejected@example.com";
	testEmails.push(email);
	const headers = await signUpAndGetCookieHeaders(email);
	const owner = await getCurrentUser(headers);

	await insertHotel(owner?.id as string, {
		status: "rejected",
		moderationReason: "Missing photos",
	});

	const listings = await getOwnerListings(owner?.id as string);
	expect(listings).toHaveLength(1);
	expect(listings[0].status).toBe("rejected");
	expect(listings[0].moderationReason).toBe("Missing photos");
});
