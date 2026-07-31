import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import type {
	CatalogPage as CatalogPageResult,
	CatalogResult,
} from "@/lib/discovery/catalog-query";
import { getCatalogResults } from "@/lib/discovery/catalog-query";
import messages from "../../../messages/ru.json";
import Home from "./page";

vi.mock("next/navigation", () => ({
	useRouter: () => ({ push: vi.fn() }),
}));

// getTranslations (next-intl/server) refuses to run under jsdom's simulated
// browser environment (window is defined) — same class of check as
// @vercel/blob/client's anti-leak-to-browser guard (T-3B01). Rendering the
// page's DOM output needs jsdom, so this file can't just switch to a node
// environment the way upload/route.test.ts did; mocking the server-i18n
// boundary sidesteps the conflict instead.
vi.mock("next-intl/server", () => ({
	getTranslations: async (namespace: string) => {
		const bundle = (messages as Record<string, Record<string, string>>)[
			namespace
		];
		return (key: string) => bundle[key];
	},
}));

// getCatalogResults' own correctness is proven against a real database in
// catalog-query.test.ts. Home only needs to prove it calls the shared query
// with the right default params and renders whatever it gets back — a real
// DB fixture here (as this test originally used) depends on how many OTHER
// published hotels exist in the shared, non-transactional Postgres instance
// at the moment this test happens to run, which any other file inserting
// enough hotels to exceed PAGE_SIZE (12) on an unfiltered query would break.
// Mocking removes that dependency entirely. Retrofitted during T-4T01 (Phase
// 4's exit gate) after that exact risk surfaced from its own pagination
// fixture — see STATE.md's Blocking Constraint on cross-file Postgres
// fixture visibility.
vi.mock("@/lib/discovery/catalog-query", async (importOriginal) => {
	const actual =
		await importOriginal<typeof import("@/lib/discovery/catalog-query")>();
	return { ...actual, getCatalogResults: vi.fn() };
});

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

function makeResult(overrides: Partial<CatalogResult> = {}): CatalogResult {
	return {
		id: "hotel-1",
		name: "Home Page Fixture Hotel",
		address: "1 Test St",
		latitude: 1,
		longitude: 1,
		accommodationType: "hotel",
		starCategory: null,
		coverPhotoUrl: null,
		startingPrice: null,
		avgRating: null,
		reviewCount: 0,
		amenityBadges: [],
		...overrides,
	};
}

test("renders the hero search, category shortcuts, and a published hotel without requiring a search first", async () => {
	vi.mocked(getCatalogResults).mockResolvedValue({
		results: [makeResult()],
		total: 1,
		page: 1,
		pageSize: 12,
	} satisfies CatalogPageResult);

	const element = await Home();
	render(element);

	expect(getCatalogResults).toHaveBeenCalledWith({
		sort: "popularity",
		page: 1,
	});
	expect(screen.getByRole("heading", { level: 1 })).toBeDefined();
	const hotelsShortcut = screen.getByRole("link", { name: "Отели" });
	expect(hotelsShortcut.getAttribute("href")).toBe(
		"/catalog?accommodationType=hotel",
	);
	expect(screen.getByText("Home Page Fixture Hotel")).toBeDefined();
});
