"use client";

import { ru } from "date-fns/locale";
import { useState } from "react";
import type { DateRange } from "react-day-picker";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from "@/components/ui/popover";

export type DateRangeValue = { from?: Date; to?: Date };

function formatDate(date: Date) {
	return date.toLocaleDateString("ru-RU", { day: "numeric", month: "short" });
}

/**
 * Plain controlled value/onChange — no internal-only state beyond popover
 * open/closed — so Phase 6's room-detail popup can reuse it unmodified
 * (l1-room-reservation.md §6). Rejects an end date before the start date by
 * construction: react-day-picker's range mode always normalizes { from, to }
 * to a valid order, it never hands back an inverted range.
 */
export function DateRangePicker({
	value,
	onChange,
	placeholder,
}: {
	value: DateRangeValue;
	onChange: (next: DateRangeValue) => void;
	placeholder: string;
}) {
	const [open, setOpen] = useState(false);

	const label = value.from
		? `${formatDate(value.from)} – ${value.to ? formatDate(value.to) : "…"}`
		: placeholder;

	return (
		<Popover open={open} onOpenChange={setOpen}>
			<PopoverTrigger render={<Button type="button" variant="outline" />}>
				{label}
			</PopoverTrigger>
			<PopoverContent className="w-auto p-2">
				<Calendar
					mode="range"
					selected={value as DateRange}
					onSelect={(range) => {
						onChange(range ?? {});
						if (range?.from && range?.to) setOpen(false);
					}}
					disabled={{ before: new Date() }}
					numberOfMonths={2}
					locale={ru}
				/>
			</PopoverContent>
		</Popover>
	);
}
