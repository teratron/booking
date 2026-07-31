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

function approveRequest(headers: Headers) {
	return new NextRequest("http://localhost:3000/api/admin/hotel/x/approve", {
		method: "POST",
		headers,
	});
}

test("approving a pending hotel sets status to published", async () => {
	const email = "test-approve-admin@example.com";
	testEmails.push(email);
	const { headers, userId } = await signUpAdmin(email);
	const pendingHotel = await createPendingHotel(userId);

	const response = await POST(approveRequest(headers), {
		params: Promise.resolve({ resource: "hotel", id: pendingHotel.id }),
	});
	expect(response.status).toBe(200);
	const body = await response.json();
	expect(body.status).toBe("published");

	const [updated] = await db
		.select()
		.from(hotel)
		.where(eq(hotel.id, pendingHotel.id));
	expect(updated.status).toBe("published");
});

test("approving is rejected for a non-admin session", async () => {
	const email = "test-approve-guest@example.com";
	testEmails.push(email);
	const headers = await signUpAndGetCookieHeaders(email);

	const response = await POST(approveRequest(headers), {
		params: Promise.resolve({
			resource: "hotel",
			id: "00000000-0000-0000-0000-000000000000",
		}),
	});
	expect(response.status).toBe(403);
});

test("approving an article is rejected with 404 — no moderation checkpoint for admin-authored content", async () => {
	const email = "test-approve-article@example.com";
	testEmails.push(email);
	const { headers } = await signUpAdmin(email);

	const response = await POST(approveRequest(headers), {
		params: Promise.resolve({
			resource: "article",
			id: "00000000-0000-0000-0000-000000000000",
		}),
	});
	expect(response.status).toBe(404);
});
