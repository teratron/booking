import { eq } from "drizzle-orm";
import { NextRequest } from "next/server";
import { afterEach, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import { hotel, user } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { POST } from "./route";

const testEmails: string[] = [];
const testHotelIds: string[] = [];

afterEach(async () => {
	for (const id of testHotelIds.splice(0)) {
		await db.delete(hotel).where(eq(hotel.id, id));
	}
	await deleteTestUsers(testEmails.splice(0));
});

async function createPendingHotel(ownerId: string) {
	const [row] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "Test Hotel",
			address: "Test Address",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "pending",
		})
		.returning();
	testHotelIds.push(row.id);
	return row;
}

async function signUpAdmin(email: string) {
	const headers = await signUpAndGetCookieHeaders(email, "Test Admin");
	await db.update(user).set({ role: "admin" }).where(eq(user.email, email));
	const [row] = await db.select().from(user).where(eq(user.email, email));
	return { headers, userId: row.id };
}

function rejectRequest(headers: Headers, body?: unknown) {
	return new NextRequest("http://localhost:3000/api/admin/hotel/x/reject", {
		method: "POST",
		headers: new Headers({
			...Object.fromEntries(headers.entries()),
			"content-type": "application/json",
		}),
		body: body === undefined ? undefined : JSON.stringify(body),
	});
}

test("rejecting a pending hotel sets status to rejected and persists the reason", async () => {
	const email = "test-reject-admin@example.com";
	testEmails.push(email);
	const { headers, userId } = await signUpAdmin(email);
	const pendingHotel = await createPendingHotel(userId);

	const response = await POST(
		rejectRequest(headers, { reason: "Missing required photos" }),
		{ params: Promise.resolve({ resource: "hotel", id: pendingHotel.id }) },
	);
	expect(response.status).toBe(200);
	const body = await response.json();
	expect(body.status).toBe("rejected");
	expect(body.moderationReason).toBe("Missing required photos");

	const [updated] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, pendingHotel.id));
	expect(updated.status).toBe("rejected");
	expect(updated.moderationReason).toBe("Missing required photos");
});

test("rejecting without a reason is rejected with 400", async () => {
	const email = "test-reject-no-reason@example.com";
	testEmails.push(email);
	const { headers, userId } = await signUpAdmin(email);
	const pendingHotel = await createPendingHotel(userId);

	const response = await POST(rejectRequest(headers, { reason: "  " }), {
		params: Promise.resolve({ resource: "hotel", id: pendingHotel.id }),
	});
	expect(response.status).toBe(400);

	const [unchanged] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, pendingHotel.id));
	expect(unchanged.status).toBe("pending");
});

test("rejecting is rejected for a non-admin session", async () => {
	const email = "test-reject-guest@example.com";
	testEmails.push(email);
	const headers = await signUpAndGetCookieHeaders(email);

	const response = await POST(
		rejectRequest(headers, { reason: "Any reason" }),
		{
			params: Promise.resolve({
				resource: "hotel",
				id: "00000000-0000-0000-0000-000000000000",
			}),
		},
	);
	expect(response.status).toBe(403);
});
