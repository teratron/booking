import { betterAuth } from "better-auth";
import { drizzleAdapter } from "better-auth/adapters/drizzle";
import { db } from "@/lib/db/client";
import * as schema from "@/lib/db/schema";

export const auth = betterAuth({
	database: drizzleAdapter(db, {
		provider: "pg",
		schema,
	}),
	emailAndPassword: {
		enabled: true,
	},
	user: {
		additionalFields: {
			role: {
				type: ["guest", "owner", "admin"],
				required: false,
				defaultValue: "guest",
				// Server-owned: a client-supplied `role` in the sign-up/update
				// payload must never be able to escalate to `owner`/`admin`.
				input: false,
			},
		},
	},
});
