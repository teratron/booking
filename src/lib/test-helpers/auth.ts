import { inArray } from "drizzle-orm";
import { auth } from "@/lib/auth/index";
import { db } from "@/lib/db/client";
import { hotel, user } from "@/lib/db/schema";

export async function signUpAndGetCookieHeaders(
	email: string,
	name = "Test User",
) {
	const { headers: responseHeaders } = await auth.api.signUpEmail({
		body: { email, password: "TestPassword123!", name },
		returnHeaders: true,
	});
	const setCookie = responseHeaders.get("set-cookie");
	if (!setCookie) throw new Error("sign-up did not return a session cookie");
	// Forward only the cookie pair back as a request Cookie header.
	const cookiePair = setCookie.split(";")[0];
	return new Headers({ cookie: cookiePair });
}

export async function deleteTestUsers(emails: string[]) {
	if (emails.length === 0) return;
	// hotel.owner_id has no onDelete cascade (an owned hotel outliving its
	// owner is a real-world integrity concern, not a test-only default) — a
	// test user that submitted a listing (Phase 3) must have it removed
	// first, or this delete fails with a foreign-key violation.
	const owners = await db
		.select({ id: user.id })
		.from(user)
		.where(inArray(user.email, emails));
	if (owners.length > 0) {
		await db.delete(hotel).where(
			inArray(
				hotel.ownerId,
				owners.map((owner) => owner.id),
			),
		);
	}
	await db.delete(user).where(inArray(user.email, emails));
}
