import { expect, test } from "vitest";
import {
	buildCatalogHref,
	parseCatalogQueryInput,
} from "@/lib/discovery/catalog-url";

test("parseCatalogQueryInput reads scalar params and collects array params", () => {
	const query = parseCatalogQueryInput({
		destination: "Kyiv",
		accommodationType: "hotel",
		minStarRating: "4",
		sort: "price",
		page: "2",
		amenityIds: ["a", "b"],
		roomAmenityIds: "c",
	});

	expect(query).toEqual({
		destination: "Kyiv",
		accommodationType: "hotel",
		minStarRating: "4",
		minPrice: undefined,
		maxPrice: undefined,
		amenityIds: ["a", "b"],
		roomAmenityIds: ["c"],
		sort: "price",
		page: "2",
	});
});

test("parseCatalogQueryInput takes the first value when Next.js parses a repeated scalar param", () => {
	const query = parseCatalogQueryInput({ destination: ["Kyiv", "Lviv"] });
	expect(query.destination).toBe("Kyiv");
});

test("parseCatalogQueryInput normalizes an empty-string scalar to undefined", () => {
	const query = parseCatalogQueryInput({
		destination: "",
		accommodationType: "",
		minStarRating: "",
		minPrice: "",
	});
	expect(query.destination).toBeUndefined();
	expect(query.accommodationType).toBeUndefined();
	expect(query.minStarRating).toBeUndefined();
	expect(query.minPrice).toBeUndefined();
});

test("buildCatalogHref preserves existing params and merges in overrides", () => {
	const href = buildCatalogHref(
		{ destination: "Kyiv", sort: "popularity" },
		{ sort: "price" },
	);
	expect(href).toBe("/catalog?destination=Kyiv&sort=price");
});

test("buildCatalogHref resets pagination on a non-page override", () => {
	const href = buildCatalogHref(
		{ destination: "Kyiv", sort: "popularity", page: "3" },
		{ sort: "price" },
	);
	expect(href).toBe("/catalog?destination=Kyiv&sort=price");
});

test("buildCatalogHref keeps an explicit page override", () => {
	const href = buildCatalogHref(
		{ destination: "Kyiv", sort: "price", page: "1" },
		{ page: "2" },
	);
	expect(href).toBe("/catalog?destination=Kyiv&sort=price&page=2");
});

test("buildCatalogHref preserves multi-value array params untouched by the override", () => {
	const href = buildCatalogHref(
		{ amenityIds: ["a", "b"], sort: "popularity" },
		{ sort: "price" },
	);
	expect(href).toBe("/catalog?amenityIds=a&amenityIds=b&sort=price");
});

test("buildCatalogHref removes a param when its override is undefined", () => {
	const href = buildCatalogHref(
		{ destination: "Kyiv", accommodationType: "hotel" },
		{ accommodationType: undefined },
	);
	expect(href).toBe("/catalog?destination=Kyiv");
});

test("buildCatalogHref returns a bare path when no params remain", () => {
	const href = buildCatalogHref({ sort: "popularity" }, { sort: undefined });
	expect(href).toBe("/catalog");
});
