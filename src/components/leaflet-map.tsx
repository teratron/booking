"use client";

import L from "leaflet";
import "leaflet/dist/leaflet.css";
import Link from "next/link";
import { MapContainer, Marker, Popup, TileLayer } from "react-leaflet";

export type MapPin = {
	id: string;
	name: string;
	latitude: number;
	longitude: number;
};

// Leaflet's default marker icon paths are relative to the package's own
// directory layout and silently 404 once bundled by Next/webpack (a
// well-documented Leaflet-in-a-bundler issue, not specific to this
// project) — repoint them at the same version's CDN-hosted images.
delete (L.Icon.Default.prototype as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({
	iconRetinaUrl:
		"https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png",
	iconUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
	shadowUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png",
});

// Ukraine's approximate centroid — this project's initial market — used only
// as the map's starting viewport when there's more than one pin to frame; it
// has no bearing on which hotels appear.
const DEFAULT_CENTER: [number, number] = [48.3794, 31.1656];
const DEFAULT_ZOOM = 6;

/**
 * Shared between the Catalog map (T-4C03, many pins from a filtered result
 * set) and the Hotel Profile location map (T-5B01, exactly one pin at the
 * hotel's own coordinates) — same rendering concern, only the pin count and
 * caller-side zoom/center differ.
 */
export default function LeafletMap({
	pins,
	center = DEFAULT_CENTER,
	zoom = DEFAULT_ZOOM,
}: {
	pins: MapPin[];
	center?: [number, number];
	zoom?: number;
}) {
	return (
		<MapContainer
			center={center}
			zoom={zoom}
			scrollWheelZoom={false}
			className="h-96 w-full rounded-xl"
		>
			<TileLayer
				attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
				url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
			/>
			{pins.map((pin) => (
				<Marker key={pin.id} position={[pin.latitude, pin.longitude]}>
					<Popup>
						<Link href={`/hotel/${pin.id}`}>{pin.name}</Link>
					</Popup>
				</Marker>
			))}
		</MapContainer>
	);
}
