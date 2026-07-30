import { auth } from "./index";

type ActorRole = "guest" | "owner" | "admin";

/**
 * Resolves the current session from a Headers object. Callers inside a
 * Server Component or Route Handler pass `await headers()` from
 * `next/headers` — kept out of this module so it stays plain, testable
 * server code with no Next.js request-scope dependency.
 */
export async function getSessionFromHeaders(requestHeaders: Headers) {
	return auth.api.getSession({ headers: requestHeaders });
}

/** Returns the current user, or `null` if there is no session. */
export async function getCurrentUser(requestHeaders: Headers) {
	const session = await getSessionFromHeaders(requestHeaders);
	return session?.user ?? null;
}

/**
 * Returns the current user only if their role exactly matches `role`, or
 * `null` otherwise (no session, or a session with a different role). Roles
 * are three distinct actors, not a hierarchy — an `admin` session does not
 * satisfy an `owner` requirement.
 */
export async function requireRole(requestHeaders: Headers, role: ActorRole) {
	const user = await getCurrentUser(requestHeaders);
	if (!user || user.role !== role) {
		return null;
	}
	return user;
}
