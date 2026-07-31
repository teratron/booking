import { eq } from "drizzle-orm";
import { auth } from "@/lib/auth/index";
import { db } from "@/lib/db/client";
import { user } from "@/lib/db/schema";

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
	for (const email of emails) {
		await db.delete(user).where(eq(user.email, email));
	}
}
