import { drizzle } from "drizzle-orm/node-postgres";
import { Pool } from "pg";
import * as schema from "./schema";

export const pool = new Pool({
	connectionString: process.env.DATABASE_URL,
	// Vitest isolates modules per test file, so each file gets its own Pool
	// instance — at pg's default max of 10, ~25+ files running in parallel
	// can request well over Postgres's max_connections (100), and some tests
	// stall waiting for a slot. Cap it under test only; production keeps pg's
	// default sizing.
	max: process.env.VITEST ? 3 : undefined,
});

export const db = drizzle(pool, { schema });
