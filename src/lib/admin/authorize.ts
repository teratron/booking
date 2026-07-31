import { NextResponse } from "next/server";
import { getCurrentUser } from "@/lib/auth/session";

// The REST surface is the security boundary for the admin back office (it is
// a client-side app and therefore cannot enforce this itself) — every route
// handler under app/api/admin/ must call this before touching data.
export async function requireAdmin(requestHeaders: Headers) {
	const user = await getCurrentUser(requestHeaders);
	if (!user) {
		return {
			user: null,
			response: NextResponse.json({ error: "Unauthorized" }, { status: 401 }),
		};
	}
	if (user.role !== "admin") {
		return {
			user: null,
			response: NextResponse.json({ error: "Forbidden" }, { status: 403 }),
		};
	}
	return { user, response: null };
}
