import { eq, type SQL } from "drizzle-orm";
import type { PgColumn, PgTable } from "drizzle-orm/pg-core";
import { NextResponse } from "next/server";
import { db } from "@/lib/db/client";

// Shared by PUT/approve/reject: applies a column update, returns the updated
// row as JSON, or a 404 response if no row matched the id.
export async function applyUpdate(
	table: PgTable,
	idColumn: PgColumn,
	id: string,
	updates: Record<string, unknown>,
): Promise<NextResponse> {
	const [row] = await db
		.update(table)
		.set(updates)
		.where(eq(idColumn, id) as SQL)
		.returning();
	if (!row) {
		return NextResponse.json({ error: "Not found" }, { status: 404 });
	}

	return NextResponse.json(row);
}
