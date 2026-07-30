import { sql } from "drizzle-orm";
import { expect, test } from "vitest";
import { db } from "./client";

test("db client connects to PostgreSQL", async () => {
	const result = await db.execute(sql`select 1 as value`);
	expect(result.rows[0]).toEqual({ value: 1 });
});
