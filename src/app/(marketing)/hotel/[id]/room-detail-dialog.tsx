"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import {
	DateRangePicker,
	type DateRangeValue,
} from "@/components/date-range-picker";
import { GuestCountPicker } from "@/components/guest-count-picker";
import { Button } from "@/components/ui/button";
import {
	Dialog,
	DialogContent,
	DialogFooter,
	DialogHeader,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog";
import type { HotelProfileRoom } from "@/lib/hotel-profile/hotel-profile-query";
import {
	checkRoomAvailabilityAction,
	createReservationAction,
	getRoomDetailAction,
} from "@/lib/reservation/actions";
import type { RoomDetail } from "@/lib/reservation/reservation-query";
import type { CreateReservationResult } from "@/lib/reservation/schema";

type ReservationErrorCode = Extract<
	CreateReservationResult,
	{ success: false }
>["error"];

type Availability = "idle" | "checking" | "available" | "unavailable";

export type RoomBookingLabels = {
	triggerLabel: string;
	datesPlaceholder: string;
	guestsLabel: string;
	decreaseGuestsLabel: string;
	increaseGuestsLabel: string;
	amenitiesTitle: string;
	bedConfigurationPrefix: string;
	checkingLabel: string;
	availableLabel: string;
	unavailableLabel: string;
	reserveLabel: string;
	reservingLabel: string;
	successTitle: string;
	successBody: string;
	successLinkLabel: string;
	signInLinkLabel: string;
	errorMessages: Record<ReservationErrorCode, string>;
};

// UTC-based Date#toISOString would shift a local-midnight selection to the
// previous day for any positive UTC offset (all of Russia) — format from
// local getters instead to keep the picked calendar day intact.
function toDateKey(date: Date): string {
	const year = date.getFullYear();
	const month = String(date.getMonth() + 1).padStart(2, "0");
	const day = String(date.getDate()).padStart(2, "0");
	return `${year}-${month}-${day}`;
}

/**
 * Base UI Dialog wiring Phase 5's intentionally-inert room cards to the
 * actual booking flow (T-6B01/T-6B02, built as one unit — a Reserve button
 * with nothing to submit to isn't a real deliverable). One independent
 * Dialog instance per room card; closed instances render only the trigger.
 */
export function RoomDetailDialog({
	room,
	capacityLabel,
	priceLabel,
	labels,
}: {
	room: HotelProfileRoom;
	capacityLabel: string;
	priceLabel: string;
	labels: RoomBookingLabels;
}) {
	const [open, setOpen] = useState(false);
	const [detail, setDetail] = useState<RoomDetail | null>(null);
	const [dates, setDates] = useState<DateRangeValue>({});
	const [guests, setGuests] = useState(1);
	const [availability, setAvailability] = useState<Availability>("idle");
	const [submitting, setSubmitting] = useState(false);
	const [result, setResult] = useState<CreateReservationResult | null>(null);
	const availabilityRequestId = useRef(0);

	useEffect(() => {
		if (!open) return;
		getRoomDetailAction(room.id).then((next) => setDetail(next ?? null));
	}, [open, room.id]);

	useEffect(() => {
		if (!dates.from || !dates.to) {
			setAvailability("idle");
			return;
		}
		const checkIn = toDateKey(dates.from);
		const checkOut = toDateKey(dates.to);
		const thisRequest = ++availabilityRequestId.current;
		setAvailability("checking");
		checkRoomAvailabilityAction(room.id, checkIn, checkOut).then(
			(available) => {
				if (thisRequest !== availabilityRequestId.current) return;
				setAvailability(available ? "available" : "unavailable");
			},
		);
	}, [dates.from, dates.to, room.id]);

	function handleOpenChange(next: boolean) {
		setOpen(next);
		if (!next) {
			setDetail(null);
			setDates({});
			setGuests(1);
			setAvailability("idle");
			setResult(null);
		}
	}

	function handleDatesChange(next: DateRangeValue) {
		setDates(next);
		setResult(null);
	}

	function handleGuestsChange(next: number) {
		setGuests(next);
		setResult(null);
	}

	async function handleReserve() {
		if (!dates.from || !dates.to) return;
		setSubmitting(true);
		const outcome = await createReservationAction({
			roomId: room.id,
			checkIn: toDateKey(dates.from),
			checkOut: toDateKey(dates.to),
			guestCount: guests,
		});
		setResult(outcome);
		setSubmitting(false);
	}

	const canReserve = availability === "available" && !submitting;

	return (
		<Dialog open={open} onOpenChange={handleOpenChange}>
			<DialogTrigger render={<Button className="w-full" />}>
				{labels.triggerLabel}
			</DialogTrigger>
			<DialogContent className="sm:max-w-md">
				<DialogHeader>
					<DialogTitle>{room.name}</DialogTitle>
				</DialogHeader>

				<div className="space-y-4">
					<RoomMediaAndPricing
						room={room}
						capacityLabel={capacityLabel}
						priceLabel={priceLabel}
					/>
					<RoomDetailInfo detail={detail} labels={labels} />

					<DateRangePicker
						value={dates}
						onChange={handleDatesChange}
						placeholder={labels.datesPlaceholder}
					/>
					<GuestCountPicker
						value={guests}
						onChange={handleGuestsChange}
						label={labels.guestsLabel}
						decreaseLabel={labels.decreaseGuestsLabel}
						increaseLabel={labels.increaseGuestsLabel}
						max={room.guestCapacity}
					/>

					<AvailabilityStatus availability={availability} labels={labels} />
					<ReservationOutcome result={result} labels={labels} />
				</div>

				{!result?.success ? (
					<DialogFooter>
						<Button disabled={!canReserve} onClick={handleReserve}>
							{submitting ? labels.reservingLabel : labels.reserveLabel}
						</Button>
					</DialogFooter>
				) : null}
			</DialogContent>
		</Dialog>
	);
}

function RoomMediaAndPricing({
	room,
	capacityLabel,
	priceLabel,
}: {
	room: HotelProfileRoom;
	capacityLabel: string;
	priceLabel: string;
}) {
	return (
		<>
			{room.coverPhotoUrl ? (
				<div className="relative aspect-4/3 w-full overflow-hidden rounded-lg bg-muted">
					<Image
						src={room.coverPhotoUrl}
						alt={room.name}
						fill
						sizes="(min-width: 640px) 24rem, 100vw"
						className="object-cover"
					/>
				</div>
			) : null}
			<div className="flex items-center justify-between text-sm">
				<span>{capacityLabel}</span>
				<span className="font-medium">{priceLabel}</span>
			</div>
		</>
	);
}

function RoomDetailInfo({
	detail,
	labels,
}: {
	detail: RoomDetail | null;
	labels: Pick<RoomBookingLabels, "bedConfigurationPrefix" | "amenitiesTitle">;
}) {
	if (!detail) return null;
	return (
		<>
			{detail.bedConfiguration ? (
				<p className="text-sm text-muted-foreground">
					{labels.bedConfigurationPrefix} {detail.bedConfiguration}
				</p>
			) : null}
			{detail.amenities.length > 0 ? (
				<div className="space-y-1">
					<p className="text-sm font-medium">{labels.amenitiesTitle}</p>
					<ul className="grid grid-cols-2 gap-1 text-sm text-muted-foreground">
						{detail.amenities.map((amenity) => (
							<li key={amenity.id}>{amenity.name}</li>
						))}
					</ul>
				</div>
			) : null}
		</>
	);
}

function AvailabilityStatus({
	availability,
	labels,
}: {
	availability: Availability;
	labels: Pick<
		RoomBookingLabels,
		"checkingLabel" | "availableLabel" | "unavailableLabel"
	>;
}) {
	if (availability === "checking") {
		return (
			<p className="text-sm text-muted-foreground">{labels.checkingLabel}</p>
		);
	}
	if (availability === "available") {
		return <p className="text-sm text-primary">{labels.availableLabel}</p>;
	}
	if (availability === "unavailable") {
		return (
			<p className="text-sm text-destructive">{labels.unavailableLabel}</p>
		);
	}
	return null;
}

function ReservationOutcome({
	result,
	labels,
}: {
	result: CreateReservationResult | null;
	labels: Pick<
		RoomBookingLabels,
		| "errorMessages"
		| "signInLinkLabel"
		| "successTitle"
		| "successBody"
		| "successLinkLabel"
	>;
}) {
	if (!result) return null;
	if (!result.success) {
		return (
			<p role="alert" className="text-sm text-destructive">
				{labels.errorMessages[result.error]}
				{result.error === "UNAUTHENTICATED" ? (
					<>
						{" "}
						<Link href="/sign-in" className="underline">
							{labels.signInLinkLabel}
						</Link>
					</>
				) : null}
			</p>
		);
	}
	return (
		<div className="space-y-1 text-sm">
			<p className="font-medium">{labels.successTitle}</p>
			<p className="text-muted-foreground">{labels.successBody}</p>
			<Link
				href="/account/reservations"
				className="text-primary underline underline-offset-4"
			>
				{labels.successLinkLabel}
			</Link>
		</div>
	);
}
