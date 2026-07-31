import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import type { MapPin } from "./leaflet-map";
import LeafletMap from "./leaflet-map";

// react-leaflet's MapContainer drives a real Leaflet map instance (canvas
// layout, ResizeObserver, tile image loading) that jsdom doesn't meaningfully
// support — Leaflet itself is a well-tested third-party library, so this
// test only needs to prove OUR component's logic (one marker per pin, each
// popup carrying the hotel's real name/link) rather than Leaflet's own
// rendering internals. Stand-in components expose exactly the props this
// test cares about as plain DOM.
vi.mock("react-leaflet", () => ({
	MapContainer: ({ children }: { children: React.ReactNode }) => (
		<div>{children}</div>
	),
	TileLayer: () => null,
	Marker: ({
		children,
		position,
	}: {
		children: React.ReactNode;
		position: [number, number];
	}) => (
		<div data-testid="marker" data-lat={position[0]} data-lng={position[1]}>
			{children}
		</div>
	),
	Popup: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock("leaflet", () => ({
	default: {
		Icon: {
			Default: {
				prototype: {},
				mergeOptions: vi.fn(),
			},
		},
	},
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

function pin(overrides: Partial<MapPin> = {}): MapPin {
	return {
		id: "hotel-1",
		name: "Fixture Hotel",
		latitude: 1,
		longitude: 2,
		...overrides,
	};
}

test("renders one marker per pin", () => {
	render(
		<LeafletMap
			pins={[pin({ id: "a" }), pin({ id: "b" }), pin({ id: "c" })]}
		/>,
	);

	expect(screen.getAllByTestId("marker")).toHaveLength(3);
});

test("each marker's popup links to the hotel's own page by real identity, not a placeholder", () => {
	render(
		<LeafletMap
			pins={[
				pin({
					id: "hotel-42",
					name: "Grand Palace",
					latitude: 10,
					longitude: 20,
				}),
			]}
		/>,
	);

	const link = screen.getByRole("link", { name: "Grand Palace" });
	expect(link.getAttribute("href")).toBe("/hotel/hotel-42");

	const marker = screen.getByTestId("marker");
	expect(marker.getAttribute("data-lat")).toBe("10");
	expect(marker.getAttribute("data-lng")).toBe("20");
});

test("renders zero markers for an empty pin list", () => {
	render(<LeafletMap pins={[]} />);

	expect(screen.queryAllByTestId("marker")).toHaveLength(0);
});
