import { eq } from "drizzle-orm";
import { NextRequest } from "next/server";
import { afterEach, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import { hotel, user } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { GET, PUT } from "./route";

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

function getRequest(headers: Headers, id: string) {
	return new NextRequest(`http://localhost:3000/api/admin/hotel/${id}`, {
		headers,
	});
}

function putRequest(headers: Headers, id: string, body: unknown) {
	return new NextRequest(`http://localhost:3000/api/admin/hotel/${id}`, {
		method: "PUT",
		headers: new Headers({
			...Object.fromEntries(headers.entries()),
			"content-type": "application/json",
		}),
		body: JSON.stringify(body),
	});
}

const MISSING_ID = "00000000-0000-0000-0000-000000000000";

test("GET a single record is rejected with 401 when unauthenticated", async () => {
	const response = await GET(getRequest(new Headers(), MISSING_ID), {
		params: Promise.resolve({ resource: "hotel", id: MISSING_ID }),
	});
	expect(response.status).toBe(401);
});

test("GET a single pending record is rejected with 403 for a guest session — pending content is not publicly readable", async () => {
	const email = "test-getone-guest@example.com";
	testEmails.push(email);
	const headers = await signUpAndGetCookieHeaders(email);

	const response = await GET(getRequest(headers, MISSING_ID), {
		params: Promise.resolve({ resource: "hotel", id: MISSING_ID }),
	});
	expect(response.status).toBe(403);
});

test("GET a single record succeeds for an admin session", async () => {
	const email = "test-getone-admin@example.com";
	testEmails.push(email);
	const { headers, userId } = await signUpAdmin(email);
	const pendingHotel = await createPendingHotel(userId);

	const response = await GET(getRequest(headers, pendingHotel.id), {
		params: Promise.resolve({ resource: "hotel", id: pendingHotel.id }),
	});
	expect(response.status).toBe(200);
	const body = await response.json();
	expect(body.id).toBe(pendingHotel.id);
	expect(body.status).toBe("pending");
});

test("PUT is rejected with 403 for a guest session — the transition is enforced server-side, not by the admin UI", async () => {
	const email = "test-put-guest@example.com";
	testEmails.push(email);
	const headers = await signUpAndGetCookieHeaders(email);

	const response = await PUT(
		putRequest(headers, MISSING_ID, { name: "Hacked" }),
		{ params: Promise.resolve({ resource: "hotel", id: MISSING_ID }) },
	);
	expect(response.status).toBe(403);
});
