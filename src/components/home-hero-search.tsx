"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import {
	DateRangePicker,
	type DateRangeValue,
} from "@/components/date-range-picker";
import { GuestCountPicker } from "@/components/guest-count-picker";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

function toDateParam(date: Date) {
	return date.toISOString().slice(0, 10);
}

export function HomeHeroSearch({
	destinationPlaceholder,
	datesPlaceholder,
	guestsLabel,
	decreaseGuestsLabel,
	increaseGuestsLabel,
	submitLabel,
}: {
	destinationPlaceholder: string;
	datesPlaceholder: string;
	guestsLabel: string;
	decreaseGuestsLabel: string;
	increaseGuestsLabel: string;
	submitLabel: string;
}) {
	const router = useRouter();
	const [destination, setDestination] = useState("");
	const [dates, setDates] = useState<DateRangeValue>({});
	const [guests, setGuests] = useState(2);

	function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault();
		const params = new URLSearchParams();
		if (destination.trim()) params.set("destination", destination.trim());
		if (dates.from) params.set("checkIn", toDateParam(dates.from));
		if (dates.to) params.set("checkOut", toDateParam(dates.to));
		params.set("guests", String(guests));
		router.push(`/catalog?${params.toString()}`);
	}

	return (
		<form
			onSubmit={handleSubmit}
			className="flex flex-wrap items-end gap-3 rounded-xl bg-card p-4 ring-1 ring-foreground/10"
		>
			<div className="flex min-w-48 flex-1 flex-col gap-1.5">
				<Input
					value={destination}
					onChange={(event) => setDestination(event.target.value)}
					placeholder={destinationPlaceholder}
					aria-label={destinationPlaceholder}
				/>
			</div>
			<DateRangePicker
				value={dates}
				onChange={setDates}
				placeholder={datesPlaceholder}
			/>
			<GuestCountPicker
				value={guests}
				onChange={setGuests}
				label={guestsLabel}
				decreaseLabel={decreaseGuestsLabel}
				increaseLabel={increaseGuestsLabel}
			/>
			<Button type="submit">{submitLabel}</Button>
		</form>
	);
}
