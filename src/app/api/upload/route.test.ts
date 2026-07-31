// @vitest-environment node
//
// `@vercel/blob/client`'s server-side token generation explicitly refuses to
// run when a `window` global exists (a guard against leaking the read-write
// token into client bundles) — the project's default jsdom test environment
// provides one, so this file overrides it back to plain Node.
import { NextRequest } from "next/server";
import { afterEach, expect, test, vi } from "vitest";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { POST } from "./route";

const testEmails: string[] = [];

afterEach(async () => {
	vi.unstubAllEnvs();
	await deleteTestUsers(testEmails.splice(0));
});

function generateTokenRequest(headers: Headers) {
	return new NextRequest("http://localhost:3000/api/upload", {
		method: "POST",
		headers,
		body: JSON.stringify({
			type: "blob.generate-client-token",
			payload: {
				pathname: "test-photo.jpg",
				multipart: false,
				clientPayload: null,
			},
		}),
	});
}

test("rejects an unauthenticated request", async () => {
	const response = await POST(generateTokenRequest(new Headers()));
	expect(response.status).toBe(401);
});

test("an authenticated request returns a well-formed client token", async () => {
	// A syntactically valid read-write token (vercel_blob_rw_<storeId>_<secret>)
	// is enough to exercise the real code path: client-token generation is a
	// local HMAC signature over the read-write token, not a network call to
	// Vercel — no real Vercel Blob store is needed to prove this route works.
	vi.stubEnv("BLOB_READ_WRITE_TOKEN", "vercel_blob_rw_teststore123_testsecret");

	const email = "test-upload-auth@example.com";
	testEmails.push(email);
	const headers = await signUpAndGetCookieHeaders(email);

	const response = await POST(generateTokenRequest(headers));
	expect(response.status).toBe(200);

	const body = await response.json();
	expect(body.type).toBe("blob.generate-client-token");
	expect(typeof body.clientToken).toBe("string");
	expect(body.clientToken.startsWith("vercel_blob_client_")).toBe(true);
});
