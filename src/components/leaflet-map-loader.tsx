"use client";

import dynamic from "next/dynamic";
import type { MapPin } from "@/components/leaflet-map";

// Leaflet touches window/document at module load — not SSR-safe. Next.js
// only allows `ssr: false` inside a Client Component, not directly in the
// Server Component page, so this thin wrapper exists solely to host that
// dynamic import; leaflet-map.tsx holds the actual map implementation.
const LeafletMap = dynamic(() => import("./leaflet-map"), {
	ssr: false,
	loading: () => (
		<div className="h-96 w-full animate-pulse rounded-xl bg-muted" />
	),
});

export function LeafletMapLoader({
	pins,
	center,
	zoom,
}: {
	pins: MapPin[];
	center?: [number, number];
	zoom?: number;
}) {
	return <LeafletMap pins={pins} center={center} zoom={zoom} />;
}
