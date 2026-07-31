import { afterEach, expect, test, vi } from "vitest";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { hotel } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";

const headersMock = vi.fn(async () => new Headers());
vi.mock("next/headers", () => ({
	headers: () => headersMock(),
}));
vi.mock("next/navigation", () => ({
	redirect: vi.fn(() => {
		throw new Error("NEXT_REDIRECT");
	}),
	notFound: vi.fn(() => {
		throw new Error("NEXT_NOT_FOUND");
	}),
}));

const testEmails: string[] = [];

afterEach(async () => {
	headersMock.mockReset();
	headersMock.mockImplementation(async () => new Headers());
	vi.clearAllMocks();
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
			status: "rejected",
			...overrides,
		})
		.returning();
	return row;
}

// See the identical note in add-hotel/new/page.test.ts — same empty-Headers
// path, same occasional full-suite timeout flakiness.
test("redirects an unauthenticated visitor to /sign-in", async () => {
	const { redirect } = await import("next/navigation");
	const { default: EditHotelListingPage } = await import("./page");

	await expect(
		EditHotelListingPage({
			params: Promise.resolve({ id: "00000000-0000-0000-0000-000000000000" }),
		}),
	).rejects.toThrow("NEXT_REDIRECT");
	expect(redirect).toHaveBeenCalledWith("/sign-in");
}, 30000);

test("returns notFound for a hotel id that doesn't exist", async () => {
	const email = "test-edit-page-missing@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	headersMock.mockImplementation(async () => requestHeaders);

	const { notFound } = await import("next/navigation");
	const { default: EditHotelListingPage } = await import("./page");

	await expect(
		EditHotelListingPage({
			params: Promise.resolve({ id: "00000000-0000-0000-0000-000000000000" }),
		}),
	).rejects.toThrow("NEXT_NOT_FOUND");
	expect(notFound).toHaveBeenCalled();
});

test("returns notFound for a hotel owned by someone else", async () => {
	const ownerEmail = "test-edit-page-owner@example.com";
	const otherEmail = "test-edit-page-other@example.com";
	testEmails.push(ownerEmail, otherEmail);
	const ownerHeaders = await signUpAndGetCookieHeaders(ownerEmail);
	const otherHeaders = await signUpAndGetCookieHeaders(otherEmail);
	const owner = await getCurrentUser(ownerHeaders);
	const listing = await insertHotel(owner?.id as string);

	headersMock.mockImplementation(async () => otherHeaders);
	const { notFound } = await import("next/navigation");
	const { default: EditHotelListingPage } = await import("./page");

	await expect(
		EditHotelListingPage({ params: Promise.resolve({ id: listing.id }) }),
	).rejects.toThrow("NEXT_NOT_FOUND");
	expect(notFound).toHaveBeenCalled();
});

test("returns notFound for a hotel that is not rejected", async () => {
	const email = "test-edit-page-pending@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	const owner = await getCurrentUser(requestHeaders);
	const listing = await insertHotel(owner?.id as string, { status: "pending" });

	headersMock.mockImplementation(async () => requestHeaders);
	const { notFound } = await import("next/navigation");
	const { default: EditHotelListingPage } = await import("./page");

	await expect(
		EditHotelListingPage({ params: Promise.resolve({ id: listing.id }) }),
	).rejects.toThrow("NEXT_NOT_FOUND");
	expect(notFound).toHaveBeenCalled();
});
