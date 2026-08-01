import { inArray } from "drizzle-orm";
import { auth } from "@/lib/auth/index";
import { db } from "@/lib/db/client";
import { hotel, reservation, user } from "@/lib/db/schema";

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
	// hotel.owner_id and reservation.guest_id have no onDelete cascade (both
	// outliving their user is a real-world integrity concern, not a
	// test-only default) — a test user that submitted a listing (Phase 3) or
	// made a booking (Phase 6) must have those rows removed first, or this
	// delete fails with a foreign-key violation.
	const owners = await db
		.select({ id: user.id })
		.from(user)
		.where(inArray(user.email, emails));
	if (owners.length > 0) {
		const ownerIds = owners.map((owner) => owner.id);
		await db.delete(hotel).where(inArray(hotel.ownerId, ownerIds));
		await db.delete(reservation).where(inArray(reservation.guestId, ownerIds));
	}
	await db.delete(user).where(inArray(user.email, emails));
}
