import type { AdminResourceName } from "@/lib/admin/resources";

// The moderation checkpoint (l1-platform-foundation.md §3) applies to
// hotel/room/review — article is admin-authored and exempt by design
// (l1-content-publishing.md), consistent with T-2C01's resource notes.
const MODERATABLE_RESOURCES: ReadonlySet<AdminResourceName> = new Set([
	"hotel",
	"room",
	"review",
]);

export function isModeratableResource(resource: AdminResourceName): boolean {
	return MODERATABLE_RESOURCES.has(resource);
}
