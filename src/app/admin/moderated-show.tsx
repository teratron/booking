"use client";

import type { ReactNode } from "react";
import { DateField, RecordField, Show } from "@/components";
import { ModerationActions } from "./moderation-actions";

// Shared shape for hotel/room/review Show views: the moderation-checkpoint
// fields (status, moderation reason, timestamps) and the Approve/Reject
// actions are identical across all three — only the resource-specific fields
// passed as children differ.
export function ModeratedShow({ children }: { children: ReactNode }) {
	return (
		<Show actions={<ModerationActions />}>
			<div className="flex flex-col gap-4">
				{children}
				<RecordField source="status" />
				<RecordField source="moderationReason" label="Moderation reason" />
				<RecordField source="createdAt" label="Created at">
					<DateField source="createdAt" showTime />
				</RecordField>
				<RecordField source="updatedAt" label="Updated at">
					<DateField source="updatedAt" showTime />
				</RecordField>
			</div>
		</Show>
	);
}
