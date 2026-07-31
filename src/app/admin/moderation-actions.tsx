"use client";

import {
	useNotify,
	useRecordContext,
	useRefresh,
	useResourceContext,
} from "ra-core";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
	Dialog,
	DialogClose,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog";

// Attaches the moderation-checkpoint actions (l1-platform-foundation.md §3) to
// a resource's Show view. Calls the dedicated approve/reject Route Handlers
// (T-2C03) directly — outside the ra-data-simple-rest CRUD contract, since
// approve/reject are constrained transitions, not free-form field edits.
export function ModerationActions() {
	const record = useRecordContext();
	const resource = useResourceContext();
	const notify = useNotify();
	const refresh = useRefresh();
	const [pending, setPending] = useState(false);
	const [rejectOpen, setRejectOpen] = useState(false);
	const [reason, setReason] = useState("");

	if (!record || !resource) return null;

	async function approve() {
		setPending(true);
		try {
			const response = await fetch(
				`/api/admin/${resource}/${record?.id}/approve`,
				{ method: "POST" },
			);
			if (!response.ok) throw new Error();
			notify("Approved", { type: "success" });
			refresh();
		} catch {
			notify("Could not approve this record", { type: "error" });
		} finally {
			setPending(false);
		}
	}

	async function reject() {
		setPending(true);
		try {
			const response = await fetch(
				`/api/admin/${resource}/${record?.id}/reject`,
				{
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ reason }),
				},
			);
			if (!response.ok) throw new Error();
			notify("Rejected", { type: "success" });
			setRejectOpen(false);
			setReason("");
			refresh();
		} catch {
			notify("Could not reject this record", { type: "error" });
		} finally {
			setPending(false);
		}
	}

	return (
		<div className="flex items-center gap-2">
			<Button variant="outline" disabled={pending} onClick={approve}>
				Approve
			</Button>
			<Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
				<DialogTrigger render={<Button variant="destructive" />}>
					Reject
				</DialogTrigger>
				<DialogContent>
					<DialogHeader>
						<DialogTitle>Reject</DialogTitle>
						<DialogDescription>
							Provide a reason — it is shown to the owner this record belongs
							to.
						</DialogDescription>
					</DialogHeader>
					<textarea
						className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
						rows={4}
						value={reason}
						onChange={(event) => setReason(event.target.value)}
					/>
					<DialogFooter>
						<DialogClose render={<Button variant="outline" />}>
							Cancel
						</DialogClose>
						<Button
							variant="destructive"
							disabled={pending || !reason.trim()}
							onClick={reject}
						>
							Confirm reject
						</Button>
					</DialogFooter>
				</DialogContent>
			</Dialog>
		</div>
	);
}
