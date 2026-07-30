"use client";

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

// No feedback backend exists yet — submitting closes the popup without
// sending anything. Wire this to a real endpoint once one is specified.
export function FeedbackPopup({
	triggerLabel,
	title,
	description,
	nameLabel,
	messageLabel,
	submitLabel,
	cancelLabel,
}: {
	triggerLabel: string;
	title: string;
	description: string;
	nameLabel: string;
	messageLabel: string;
	submitLabel: string;
	cancelLabel: string;
}) {
	const [open, setOpen] = useState(false);

	return (
		<Dialog open={open} onOpenChange={setOpen}>
			<DialogTrigger render={<Button variant="ghost" />}>
				{triggerLabel}
			</DialogTrigger>
			<DialogContent>
				<DialogHeader>
					<DialogTitle>{title}</DialogTitle>
					<DialogDescription>{description}</DialogDescription>
				</DialogHeader>
				<form
					className="flex flex-col gap-3"
					onSubmit={(event) => {
						event.preventDefault();
						setOpen(false);
					}}
				>
					<label className="flex flex-col gap-1 text-sm">
						{nameLabel}
						<input
							name="name"
							type="text"
							className="rounded-md border border-input bg-background px-3 py-2 text-sm"
						/>
					</label>
					<label className="flex flex-col gap-1 text-sm">
						{messageLabel}
						<textarea
							name="message"
							rows={4}
							className="rounded-md border border-input bg-background px-3 py-2 text-sm"
						/>
					</label>
					<DialogFooter>
						<DialogClose render={<Button variant="outline" type="button" />}>
							{cancelLabel}
						</DialogClose>
						<Button type="submit">{submitLabel}</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
