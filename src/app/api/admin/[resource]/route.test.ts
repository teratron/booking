import { NextRequest } from "next/server";
import { afterEach, expect, test } from "vitest";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { GET } from "./route";

const testEmails: string[] = [];

afterEach(async () => {
	await deleteTestUsers(testEmails.splice(0));
});

function listRequest(headers: Headers) {
	return new NextRequest("http://localhost:3000/api/admin/hotel", {
		headers,
	});
}

test("an unauthenticated request is rejected with 401", async () => {
	const response = await GET(listRequest(new Headers()), {
		params: Promise.resolve({ resource: "hotel" }),
	});
	expect(response.status).toBe(401);
});

test("a guest-role session is rejected with 403", async () => {
	const email = "test-admin-route-guest@example.com";
	testEmails.push(email);
	const headers = await signUpAndGetCookieHeaders(email);

	const response = await GET(listRequest(headers), {
		params: Promise.resolve({ resource: "hotel" }),
	});
	expect(response.status).toBe(403);
});

test("a table outside the admin resource allow-list is rejected with 404, not exposed", async () => {
	const response = await GET(
		new NextRequest("http://localhost:3000/api/admin/user", {
			headers: new Headers(),
		}),
		{ params: Promise.resolve({ resource: "user" }) },
	);
	expect(response.status).toBe(404);
});
