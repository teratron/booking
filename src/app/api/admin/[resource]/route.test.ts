import { eq } from "drizzle-orm";
import { NextRequest } from "next/server";
import { afterEach, expect, test } from "vitest";
import { auth } from "@/lib/auth/index";
import { db } from "@/lib/db/client";
import { user } from "@/lib/db/schema";
import { GET } from "./route";

const testEmails: string[] = [];

afterEach(async () => {
	for (const email of testEmails.splice(0)) {
		await db.delete(user).where(eq(user.email, email));
	}
});

async function signUpAndGetCookieHeaders(email: string) {
	const { headers: responseHeaders } = await auth.api.signUpEmail({
		body: { email, password: "TestPassword123!", name: "Test Admin Route" },
		returnHeaders: true,
	});
	const setCookie = responseHeaders.get("set-cookie");
	if (!setCookie) throw new Error("sign-up did not return a session cookie");
	const cookiePair = setCookie.split(";")[0];
	return new Headers({ cookie: cookiePair });
}

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
