import { and, asc, desc, eq, inArray, sql } from "drizzle-orm";
import { type NextRequest, NextResponse } from "next/server";
import { resolveAdminRequest } from "@/lib/admin/resolve-request";
import { getAdminColumn } from "@/lib/admin/resources";
import { db } from "@/lib/db/client";

// Implements the ra-data-simple-rest list contract:
// GET /:resource?sort=["field","ASC"]&range=[start,end]&filter={...}
// -> 200, body = matching rows, header Content-Range: resource start-end/total
export async function GET(
	request: NextRequest,
	{ params }: { params: Promise<{ resource: string }> },
) {
	const { resource } = await params;
	const resolved = await resolveAdminRequest(request, resource);
	if (!resolved.ok) return resolved.response;
	const { table, idColumn } = resolved;

	const url = new URL(request.url);
	const [sortField, sortOrder] = parseJsonParam(url, "sort") ?? ["id", "ASC"];
	const [start, end] = parseJsonParam(url, "range") ?? [0, 9];
	const filter = parseJsonParam(url, "filter") ?? {};

	const conditions = Object.entries(filter)
		.map(([field, value]) => {
			const column = getAdminColumn(resolved.resource, field);
			if (!column) return null;
			// getMany (react-admin's ReferenceField/ReferenceArrayField) sends
			// filter: { id: [...] } for an "IN" lookup rather than equality.
			return Array.isArray(value) ? inArray(column, value) : eq(column, value);
		})
		.filter((condition) => condition !== null);
	const whereClause = conditions.length ? and(...conditions) : undefined;

	const orderColumn = getAdminColumn(resolved.resource, sortField) ?? idColumn;
	const orderBy = sortOrder === "DESC" ? desc(orderColumn) : asc(orderColumn);

	const limit = Math.max(end - start + 1, 0);
	const [rows, [{ total }]] = await Promise.all([
		db
			.select()
			.from(table)
			.where(whereClause)
			.orderBy(orderBy)
			.limit(limit)
			.offset(start),
		db.select({ total: sql<string>`count(*)` }).from(table).where(whereClause),
	]);

	const rangeEnd = rows.length ? start + rows.length - 1 : start;
	return NextResponse.json(rows, {
		status: 200,
		headers: {
			"Content-Range": `${resource} ${start}-${rangeEnd}/${total}`,
		},
	});
}

function parseJsonParam(url: URL, name: string) {
	const raw = url.searchParams.get(name);
	if (!raw) return null;
	try {
		return JSON.parse(raw);
	} catch {
		return null;
	}
}
