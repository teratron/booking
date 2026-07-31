import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import { getHotelNews } from "@/lib/content/article-query";
import type { HotelProfile } from "@/lib/hotel-profile/hotel-profile-query";
import { getHotelProfile } from "@/lib/hotel-profile/hotel-profile-query";
import messages from "../../../../../messages/ru.json";
import HotelProfilePage from "./page";
import { RECENTLY_VIEWED_STORAGE_KEY } from "./recently-viewed";

vi.mock("next-intl/server", () => ({
	getTranslations: async (namespace: string) => {
		const bundle = (messages as Record<string, Record<string, string>>)[
			namespace
		];
		return (key: string, values?: Record<string, string | number>) => {
			const text = bundle[key];
			if (!values) return text;
			return Object.entries(values).reduce(
				(result, [name, value]) =>
					result.replaceAll(`{${name}}`, String(value)),
				text,
			);
		};
	},
}));

// getHotelProfile's own correctness (moderation checkpoint, aggregation
// shape) is proven against a real database in hotel-profile-query.test.ts
// (T-5A02) — this page only needs to prove it renders whatever it gets back
// and calls notFound() when there's nothing to render.
vi.mock("@/lib/hotel-profile/hotel-profile-query", async (importOriginal) => {
	const actual =
		await importOriginal<
			typeof import("@/lib/hotel-profile/hotel-profile-query")
		>();
	return { ...actual, getHotelProfile: vi.fn() };
});

// getHotelNews' own correctness is proven in article-query.test.ts (T-5A01).
vi.mock("@/lib/content/article-query", async (importOriginal) => {
	const actual =
		await importOriginal<typeof import("@/lib/content/article-query")>();
	return { ...actual, getHotelNews: vi.fn().mockResolvedValue([]) };
});

// The map is verified on its own in leaflet-map.test.tsx and is a
// `next/dynamic(..., {ssr:false})` client component this file's synchronous
// render() can't reliably wait on — stand in a trivial marker.
vi.mock("@/components/leaflet-map-loader", () => ({
	LeafletMapLoader: () => <div data-testid="leaflet-map-loader" />,
}));

const notFoundMock = vi.fn(() => {
	throw new Error("NEXT_NOT_FOUND");
});
vi.mock("next/navigation", () => ({
	notFound: () => notFoundMock(),
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
	window.localStorage.clear();
});

function makeProfile(overrides: Partial<HotelProfile> = {}): HotelProfile {
	return {
		id: "hotel-1",
		name: "Fixture Hotel",
		address: "1 Test St",
		latitude: 1,
		longitude: 2,
		phone: "+10000000000",
		accommodationType: "hotel",
		starCategory: 5,
		galleryPhotos: [],
		amenities: [],
		rooms: [],
		avgRating: null,
		reviewCount: 0,
		reviews: [],
		...overrides,
	};
}

test("calls notFound() for a missing/unpublished hotel", async () => {
	vi.mocked(getHotelProfile).mockResolvedValueOnce(undefined);

	await expect(
		HotelProfilePage({ params: Promise.resolve({ id: "missing" }) }),
	).rejects.toThrow();
	expect(notFoundMock).toHaveBeenCalled();
});

test("renders a full profile with gallery, rooms, amenities, and the map", async () => {
	vi.mocked(getHotelProfile).mockResolvedValueOnce(
		makeProfile({
			galleryPhotos: ["https://example.test/1.jpg"],
			amenities: [{ id: "a1", name: "Wi-Fi", group: "hotel" }],
			rooms: [
				{
					id: "r1",
					name: "Suite",
					guestCapacity: 2,
					basePrice: 150,
					coverPhotoUrl: null,
				},
			],
			avgRating: 4.5,
			reviewCount: 3,
		}),
	);

	const element = await HotelProfilePage({
		params: Promise.resolve({ id: "hotel-1" }),
	});
	render(element);

	expect(
		screen.getByRole("heading", { level: 1, name: "Fixture Hotel" }),
	).toBeDefined();
	expect(screen.getByText("1 Test St")).toBeDefined();
	expect(screen.getByText("4.5 (3)")).toBeDefined();
	expect(screen.getAllByText("Wi-Fi").length).toBeGreaterThan(0);
	expect(screen.getByText("Suite")).toBeDefined();
	expect(screen.getByText("2 чел.")).toBeDefined();
	expect(screen.getByTestId("leaflet-map-loader")).toBeDefined();
});

test("degrades independently: zero gallery/amenities/rooms/reviews still renders a usable header", async () => {
	vi.mocked(getHotelProfile).mockResolvedValueOnce(makeProfile());

	const element = await HotelProfilePage({
		params: Promise.resolve({ id: "hotel-1" }),
	});
	render(element);

	expect(
		screen.getByRole("heading", { level: 1, name: "Fixture Hotel" }),
	).toBeDefined();
	expect(screen.getByText("Нет отзывов")).toBeDefined();
	expect(screen.getByTestId("leaflet-map-loader")).toBeDefined();
});

test("renders hotel news via the shared ArticleCard, and guest reviews itemized", async () => {
	vi.mocked(getHotelProfile).mockResolvedValueOnce(
		makeProfile({
			reviews: [
				{
					id: "rev-1",
					guestName: "Jane Guest",
					guestAvatar: null,
					rating: 5,
					comment: "Wonderful stay",
					createdAt: new Date("2026-01-01"),
				},
			],
			reviewCount: 1,
			avgRating: 5,
		}),
	);
	vi.mocked(getHotelNews).mockResolvedValueOnce([
		{
			id: "article-1",
			title: "Hotel News Article",
			coverImage: "https://example.test/news.jpg",
			summary: "News summary",
			publishedAt: new Date("2026-01-10"),
		},
	]);

	const element = await HotelProfilePage({
		params: Promise.resolve({ id: "hotel-1" }),
	});
	render(element);

	expect(getHotelNews).toHaveBeenCalledWith("hotel-1");
	// ArticleCard wraps both its cover image (alt = title) and its title text
	// in separate links to the same href, so both share this accessible name.
	const newsLinks = screen.getAllByRole("link", {
		name: "Hotel News Article",
	});
	expect(newsLinks[0].getAttribute("href")).toBe("/blog/article-1");
	expect(screen.getByText("Jane Guest")).toBeDefined();
	expect(screen.getByText("Wonderful stay")).toBeDefined();
});

test("the recently-viewed rail excludes the current hotel and shows others from localStorage", async () => {
	window.localStorage.setItem(
		RECENTLY_VIEWED_STORAGE_KEY,
		JSON.stringify([
			{ id: "hotel-1", name: "Fixture Hotel" },
			{ id: "hotel-2", name: "Previously Viewed Hotel" },
		]),
	);
	vi.mocked(getHotelProfile).mockResolvedValueOnce(makeProfile());

	const element = await HotelProfilePage({
		params: Promise.resolve({ id: "hotel-1" }),
	});
	render(element);

	expect(screen.queryByRole("link", { name: "Fixture Hotel" })).toBeNull();
	const otherLink = screen.getByRole("link", {
		name: "Previously Viewed Hotel",
	});
	expect(otherLink.getAttribute("href")).toBe("/hotel/hotel-2");
});
