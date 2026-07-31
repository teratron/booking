import type { NextRequest } from "next/server";
import { NextResponse } from "next/server";
import { requireAdmin } from "@/lib/admin/authorize";
import { isModeratableResource } from "@/lib/admin/moderation";
import {
	type AdminResourceName,
	getAdminColumn,
	getAdminTable,
	normalizeAdminResourceName,
} from "@/lib/admin/resources";

type ResolvedAdminRequest =
	| {
			ok: true;
			resource: AdminResourceName;
			table: ReturnType<typeof getAdminTable>;
			idColumn: NonNullable<ReturnType<typeof getAdminColumn>>;
	  }
	| { ok: false; response: NextResponse };

// Shared by every app/api/admin/[resource] handler: validates the resource
// name, enforces the admin-role gate, and resolves the table + id column —
// the one place this boilerplate lives instead of being repeated per method.
export async function resolveAdminRequest(
	request: NextRequest,
	resource: string,
): Promise<ResolvedAdminRequest> {
	const normalized = normalizeAdminResourceName(resource);
	if (!normalized) {
		return {
			ok: false,
			response: NextResponse.json({ error: "Not found" }, { status: 404 }),
		};
	}

	const { user, response } = await requireAdmin(request.headers);
	if (!user) return { ok: false, response: response as NextResponse };

	const table = getAdminTable(normalized);
	const idColumn = getAdminColumn(normalized, "id");
	if (!idColumn) {
		throw new Error(`Admin resource "${normalized}" has no id column`);
	}

	return { ok: true, resource: normalized, table, idColumn };
}

// Shared by the approve/reject handlers: same resolution as
// resolveAdminRequest, plus the moderation-checkpoint resource gate (404 for
// article, which has no status column).
export async function resolveModerationRequest(
	request: NextRequest,
	resource: string,
): Promise<ResolvedAdminRequest> {
	const resolved = await resolveAdminRequest(request, resource);
	if (!resolved.ok) return resolved;

	if (!isModeratableResource(resolved.resource)) {
		return {
			ok: false,
			response: NextResponse.json({ error: "Not found" }, { status: 404 }),
		};
	}

	return resolved;
}
