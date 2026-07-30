import { eq } from "drizzle-orm";
import { afterEach, expect, test } from "vitest";
import { db } from "../db/client";
import { user } from "../db/schema";
import { auth } from "./index";
import { getCurrentUser, requireRole } from "./session";

const testEmails: string[] = [];

afterEach(async () => {
	for (const email of testEmails.splice(0)) {
		await db.delete(user).where(eq(user.email, email));
	}
});

async function signUpAndGetCookieHeaders(email: string) {
	const { headers: responseHeaders } = await auth.api.signUpEmail({
		body: { email, password: "TestPassword123!", name: "Test Session" },
		returnHeaders: true,
	});
	const setCookie = responseHeaders.get("set-cookie");
	if (!setCookie) throw new Error("sign-up did not return a session cookie");
	// Forward only the cookie pair back as a request Cookie header.
	const cookiePair = setCookie.split(";")[0];
	return new Headers({ cookie: cookiePair });
}

test("getCurrentUser returns null for an unauthenticated request", async () => {
	const result = await getCurrentUser(new Headers());
	expect(result).toBeNull();
});

test("getCurrentUser returns the user for an authenticated request", async () => {
	const email = "test-session-auth@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await getCurrentUser(requestHeaders);
	expect(result?.email).toBe(email);
	expect(result?.role).toBe("guest");
});

test("requireRole rejects a guest session where owner is required", async () => {
	const email = "test-session-role-gate@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const asOwner = await requireRole(requestHeaders, "owner");
	expect(asOwner).toBeNull();

	const asGuest = await requireRole(requestHeaders, "guest");
	expect(asGuest?.email).toBe(email);
});
