import { eq } from "drizzle-orm";
import { afterEach, expect, test } from "vitest";
import { db } from "../db/client";
import { user } from "../db/schema";
import { auth } from "./index";

const testEmails: string[] = [];

afterEach(async () => {
	for (const email of testEmails.splice(0)) {
		await db.delete(user).where(eq(user.email, email));
	}
});

test("a new account defaults to role 'guest'", async () => {
	const email = "test-role-default@example.com";
	testEmails.push(email);

	await auth.api.signUpEmail({
		body: { email, password: "TestPassword123!", name: "Test Default Role" },
	});

	const [row] = await db.select().from(user).where(eq(user.email, email));
	expect(row.role).toBe("guest");
});

test("a client-supplied role in the sign-up payload is ignored, not escalated", async () => {
	const email = "test-role-escalation@example.com";
	testEmails.push(email);

	await auth.api.signUpEmail({
		body: {
			email,
			password: "TestPassword123!",
			name: "Test Escalation",
			// @ts-expect-error role is intentionally not part of the accepted
			// sign-up input type (input: false) — this proves the server
			// rejects/ignores it even if a caller bypasses the type check.
			role: "admin",
		},
	});

	const [row] = await db.select().from(user).where(eq(user.email, email));
	expect(row.role).toBe("guest");
	expect(row.role).not.toBe("admin");
});
