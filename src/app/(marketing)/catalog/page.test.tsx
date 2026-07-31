import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import type {
	CatalogPage as CatalogPageResult,
	CatalogResult,
} from "@/lib/discovery/catalog-query";
import { getCatalogResults } from "@/lib/discovery/catalog-query";
import messages from "../../../../messages/ru.json";
import CatalogPage from "./page";

// Same next-intl/server jsdom conflict as (marketing)/page.test.tsx, plus
// simple {placeholder} interpolation since this page's strings (resultsCount,
// pageIndicator) actually use it.
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

// getCatalogResults' own filter/sort/pagination correctness is exhaustively
// covered against a real database in catalog-query.test.ts (T-4A01). This
// page only needs to prove it parses searchParams correctly, passes them
// through, and renders sort/pagination controls from whatever CatalogPage it
// gets back — so the DB call is mocked rather than re-proving T-4A01's SQL,
// which also avoids two concurrently-running test files racing over shared
// Postgres fixtures (a real risk: an unfiltered query in one file can see
// another file's in-flight rows).
vi.mock("@/lib/discovery/catalog-query", async (importOriginal) => {
	const actual =
		await importOriginal<typeof import("@/lib/discovery/catalog-query")>();
	return {
		...actual,
		getCatalogResults: vi.fn(),
		getCatalogMapPins: vi.fn().mockResolvedValue([]),
	};
});

// The filter sidebar (T-4C02) is verified on its own in filter-sidebar.test.tsx
// against real AmenityOption fixtures — this file only needs a canned,
// DB-free return so the page itself stays fully mocked/deterministic.
vi.mock("@/lib/discovery/amenity-facets", () => ({
	getAmenityFacets: vi
		.fn()
		.mockResolvedValue({ hotelAmenities: [], roomAmenities: [] }),
}));

// The map (T-4C03) is verified on its own in leaflet-map.test.tsx. It's also
// a `next/dynamic(..., {ssr:false})` client component, which this file's
// synchronous `render()` calls have no reliable way to wait on — stand in a
// trivial marker instead of depending on dynamic-import timing under jsdom.
vi.mock("@/components/leaflet-map-loader", () => ({
	LeafletMapLoader: () => <div data-testid="leaflet-map-loader" />,
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

function makeResult(overrides: Partial<CatalogResult> = {}): CatalogResult {
	return {
		id: "hotel-1",
		name: "Fixture Hotel",
		address: "Fixture Address",
		latitude: 1,
		longitude: 1,
		accommodationType: "hotel",
		starCategory: null,
		coverPhotoUrl: null,
		startingPrice: 100,
		avgRating: null,
		reviewCount: 0,
		amenityBadges: [],
		...overrides,
	};
}

function mockPage(overrides: Partial<CatalogPageResult>) {
	vi.mocked(getCatalogResults).mockResolvedValue({
		results: [],
		total: 0,
		page: 1,
		pageSize: 12,
		...overrides,
	});
}

test("parses searchParams into the query passed to getCatalogResults, and renders its results with an active sort pill", async () => {
	mockPage({
		results: [
			makeResult({ id: "a", name: "Alpha Hotel" }),
			makeResult({ id: "b", name: "Beta Hotel" }),
		],
		total: 2,
	});

	const element = await CatalogPage({
		searchParams: Promise.resolve({ destination: "Kyiv", sort: "popularity" }),
	});
	render(element);

	expect(getCatalogResults).toHaveBeenCalledWith(
		expect.objectContaining({ destination: "Kyiv", sort: "popularity" }),
	);
	expect(screen.getByText("Alpha Hotel")).toBeDefined();
	expect(screen.getByText("Beta Hotel")).toBeDefined();

	const activeSort = screen.getByRole("link", { name: "По популярности" });
	expect(activeSort.getAttribute("aria-current")).toBe("true");

	const priceSort = screen.getByRole("link", { name: "По цене" });
	expect(priceSort.getAttribute("aria-current")).toBeNull();
	expect(priceSort.getAttribute("href")).toBe(
		"/catalog?destination=Kyiv&sort=price",
	);
});

test("hides pagination when all results fit on one page", async () => {
	mockPage({ results: [makeResult()], total: 1 });

	const element = await CatalogPage({ searchParams: Promise.resolve({}) });
	render(element);

	expect(screen.queryByRole("navigation")).toBeNull();
});

test("on the first page, previous is disabled and next links to page 2 preserving filters", async () => {
	mockPage({ results: [makeResult()], total: 25, page: 1 });

	const element = await CatalogPage({
		searchParams: Promise.resolve({ destination: "Overflow", sort: "price" }),
	});
	render(element);

	expect(screen.queryByRole("link", { name: "Назад" })).toBeNull();
	expect(screen.getByText("Назад")).toBeDefined();

	const next = screen.getByRole("link", { name: "Вперёд" });
	expect(next.getAttribute("href")).toBe(
		"/catalog?destination=Overflow&sort=price&page=2",
	);
	expect(screen.getByText("Страница 1 из 3")).toBeDefined();
});

test("on the last page, next is disabled and previous links back one page", async () => {
	mockPage({ results: [makeResult()], total: 25, page: 3 });

	const element = await CatalogPage({
		searchParams: Promise.resolve({ page: "3" }),
	});
	render(element);

	expect(screen.queryByRole("link", { name: "Вперёд" })).toBeNull();

	const previous = screen.getByRole("link", { name: "Назад" });
	expect(previous.getAttribute("href")).toBe("/catalog?page=2");
});
