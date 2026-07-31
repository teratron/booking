"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

export const RECENTLY_VIEWED_STORAGE_KEY = "booking:recently-viewed-hotels";
const MAX_ENTRIES = 6;

export type RecentlyViewedEntry = { id: string; name: string };

/**
 * l1-hotel-profile.md §3: scoped to the visiting browser/session, not an
 * authenticated account — a plain `localStorage` list, no server-side
 * session table (see tasks/phase-5.md Decisions). The current hotel is
 * always written to the front of the stored list on view, but never shown
 * in its own rail.
 */
export function RecentlyViewedRail({
	currentHotel,
	title,
}: {
	currentHotel: RecentlyViewedEntry;
	title: string;
}) {
	const [others, setOthers] = useState<RecentlyViewedEntry[]>([]);

	useEffect(() => {
		const raw = window.localStorage.getItem(RECENTLY_VIEWED_STORAGE_KEY);
		const stored: RecentlyViewedEntry[] = raw ? JSON.parse(raw) : [];
		const withoutCurrent = stored.filter(
			(entry) => entry.id !== currentHotel.id,
		);
		setOthers(withoutCurrent);

		const updated = [currentHotel, ...withoutCurrent].slice(0, MAX_ENTRIES);
		window.localStorage.setItem(
			RECENTLY_VIEWED_STORAGE_KEY,
			JSON.stringify(updated),
		);
	}, [currentHotel]);

	if (others.length === 0) return null;

	return (
		<section className="space-y-3">
			<h2 className="text-xl font-medium">{title}</h2>
			<div className="flex flex-wrap gap-2">
				{others.map((hotel) => (
					<Link
						key={hotel.id}
						href={`/hotel/${hotel.id}`}
						className="rounded-full bg-secondary px-4 py-2 text-sm text-secondary-foreground hover:bg-secondary/80"
					>
						{hotel.name}
					</Link>
				))}
			</div>
		</section>
	);
}
