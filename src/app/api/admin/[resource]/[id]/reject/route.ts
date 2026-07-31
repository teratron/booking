import { type NextRequest, NextResponse } from "next/server";
import { applyUpdate } from "@/lib/admin/apply-update";
import { resolveModerationRequest } from "@/lib/admin/resolve-request";

type RouteParams = { params: Promise<{ resource: string; id: string }> };

// Rejects a pending hotel/room/review: status -> "rejected", persisting the
// operator's reason. A reason is required — a rejection with no explanation
// isn't useful to the owner it affects. Not exposed for article, which has
// no moderation checkpoint (T-2C01's resource notes).
export async function POST(request: NextRequest, { params }: RouteParams) {
	const { resource, id } = await params;
	const resolved = await resolveModerationRequest(request, resource);
	if (!resolved.ok) return resolved.response;

	const body = await request.json().catch(() => null);
	const reason = typeof body?.reason === "string" ? body.reason.trim() : "";
	if (!reason) {
		return NextResponse.json(
			{ error: "A rejection reason is required" },
			{ status: 400 },
		);
	}

	const { table, idColumn } = resolved;
	return applyUpdate(table, idColumn, id, {
		status: "rejected",
		moderationReason: reason,
	});
}
