import { eq } from "drizzle-orm";
import { type NextRequest, NextResponse } from "next/server";
import { applyUpdate } from "@/lib/admin/apply-update";
import { resolveAdminRequest } from "@/lib/admin/resolve-request";
import { getAdminColumn } from "@/lib/admin/resources";
import { db } from "@/lib/db/client";

type RouteParams = { params: Promise<{ resource: string; id: string }> };

export async function GET(request: NextRequest, { params }: RouteParams) {
	const { resource, id } = await params;
	const resolved = await resolveAdminRequest(request, resource);
	if (!resolved.ok) return resolved.response;
	const { table, idColumn } = resolved;

	const [row] = await db.select().from(table).where(eq(idColumn, id));
	if (!row) {
		return NextResponse.json({ error: "Not found" }, { status: 404 });
	}

	return NextResponse.json(row);
}

export async function PUT(request: NextRequest, { params }: RouteParams) {
	const { resource, id } = await params;
	const resolved = await resolveAdminRequest(request, resource);
	if (!resolved.ok) return resolved.response;
	const { table, idColumn } = resolved;

	const body = await request.json();
	// react-admin's simple-rest data provider round-trips the full record on
	// update, including the id — strip fields the resource has no column for
	// rather than letting Drizzle reject the whole update on an unknown key.
	const updates = Object.fromEntries(
		Object.entries(body).filter(([field]) =>
			Boolean(getAdminColumn(resolved.resource, field)),
		),
	);

	return applyUpdate(table, idColumn, id, updates);
}
