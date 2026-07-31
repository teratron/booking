import { eq } from "drizzle-orm";
import { afterEach, expect, test } from "vitest";
import { db } from "../db/client";
import { user } from "../db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "../test-helpers/auth";
import { getCurrentUser, requireRole } from "./session";

const testEmails: string[] = [];

afterEach(async () => {
	await deleteTestUsers(testEmails.splice(0));
});

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

// T-2T01: all three actor_role values must be assignable and readable
// through the auth layer, not merely present as an enum in the schema.
// There is no self-service escalation path (T-2A03 blocks that on purpose —
// see index.test.ts), so "assignable" here means the same way Phase 2
// actually provisions owner/admin today: an out-of-band DB write (Phase 3
// will add the real owner-onboarding flow; admin accounts are always
// provisioned this way, by design — see l2-third-party-integrations.md §5.3).
test("a user promoted to owner is readable as owner through the auth layer", async () => {
	const email = "test-session-owner@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	await db.update(user).set({ role: "owner" }).where(eq(user.email, email));

	const result = await getCurrentUser(requestHeaders);
	expect(result?.role).toBe("owner");

	const asOwner = await requireRole(requestHeaders, "owner");
	expect(asOwner?.email).toBe(email);
	const asAdmin = await requireRole(requestHeaders, "admin");
	expect(asAdmin).toBeNull();
});

test("a user promoted to admin is readable as admin through the auth layer, and does not also satisfy an owner gate", async () => {
	const email = "test-session-admin@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);
	await db.update(user).set({ role: "admin" }).where(eq(user.email, email));

	const result = await getCurrentUser(requestHeaders);
	expect(result?.role).toBe("admin");

	const asAdmin = await requireRole(requestHeaders, "admin");
	expect(asAdmin?.email).toBe(email);
	// Roles are three distinct actors, not a hierarchy (T-2A04's invariant).
	const asOwner = await requireRole(requestHeaders, "owner");
	expect(asOwner).toBeNull();
});
