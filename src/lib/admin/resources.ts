import { getTableColumns } from "drizzle-orm";
import type { PgColumn } from "drizzle-orm/pg-core";
import { article, hotel, review, room } from "@/lib/db/schema";

// The bounded set of resources the admin REST surface exposes — the four
// tables with a moderation/editorial need (l2-third-party-integrations.md
// §5.3). Deliberately not every table in the schema: `user`/`session`/
// `account` must never be reachable through this route.
const ADMIN_TABLES = { hotel, room, review, article } as const;

export type AdminResourceName = keyof typeof ADMIN_TABLES;

// react-admin's ListGuesser/ReferenceField infer a foreign key's target
// resource by pluralizing the field name (e.g. `hotelId` -> `hotels`),
// independent of how the resource itself is registered in <Resource name=…>.
// Accepting both forms here keeps T-2C01's singular contract as the primary,
// documented one while letting auto-guessed reference lookups resolve too.
const PLURAL_ALIASES: Record<string, AdminResourceName> = {
	hotels: "hotel",
	rooms: "room",
	reviews: "review",
	articles: "article",
};

export function normalizeAdminResourceName(
	value: string,
): AdminResourceName | null {
	if (Object.hasOwn(ADMIN_TABLES, value)) {
		return value as AdminResourceName;
	}
	return PLURAL_ALIASES[value] ?? null;
}

export function getAdminTable(resource: AdminResourceName) {
	return ADMIN_TABLES[resource];
}

// Resolves a request-supplied field name to the matching Drizzle column, or
// `undefined` if the resource has no such column — callers must treat an
// unresolved field as "ignore this filter/sort key" rather than guessing,
// since a raw string field name is untrusted input.
export function getAdminColumn(
	resource: AdminResourceName,
	field: string,
): PgColumn | undefined {
	const columns = getTableColumns(ADMIN_TABLES[resource]) as Record<
		string,
		PgColumn
	>;
	return columns[field];
}
