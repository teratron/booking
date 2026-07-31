import type { NextRequest } from "next/server";
import { applyUpdate } from "@/lib/admin/apply-update";
import { resolveModerationRequest } from "@/lib/admin/resolve-request";

type RouteParams = { params: Promise<{ resource: string; id: string }> };

// Approves a pending hotel/room/review: status -> "published". Not exposed
// for article, which has no moderation checkpoint (T-2C01's resource notes).
export async function POST(request: NextRequest, { params }: RouteParams) {
	const { resource, id } = await params;
	const resolved = await resolveModerationRequest(request, resource);
	if (!resolved.ok) return resolved.response;
	const { table, idColumn } = resolved;

	return applyUpdate(table, idColumn, id, { status: "published" });
}
