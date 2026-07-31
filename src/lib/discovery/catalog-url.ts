import type { CatalogQueryInput } from "@/lib/discovery/catalog-query";

export type CatalogSearchParams = Record<string, string | string[] | undefined>;

// Empty string normalizes to undefined — the filter sidebar (T-4C02) is a
// native GET <form>, so an "Any"/cleared control submits its field as an
// empty string rather than omitting it; catalogQuerySchema would otherwise
// reject that empty string (destination requires min(1), enums reject "").
function firstValue(value: string | string[] | undefined): string | undefined {
	const raw = Array.isArray(value) ? value[0] : value;
	return raw === undefined || raw === "" ? undefined : raw;
}

function arrayValue(
	value: string | string[] | undefined,
): string[] | undefined {
	if (value === undefined) return undefined;
	return Array.isArray(value) ? value : [value];
}

/**
 * Converts the raw Next.js searchParams object into T-4A01's query input
 * shape — the inverse of buildCatalogHref, together forming the
 * l1-hotel-discovery.md §2 shareable/back-navigable URL-state contract.
 * Malformed values (e.g. a hand-edited URL) are left for
 * catalogQuerySchema.parse to reject — a single validation boundary rather
 * than duplicating range/enum checks here.
 */
export function parseCatalogQueryInput(
	searchParams: CatalogSearchParams,
): CatalogQueryInput {
	return {
		destination: firstValue(searchParams.destination),
		accommodationType: firstValue(
			searchParams.accommodationType,
		) as CatalogQueryInput["accommodationType"],
		minStarRating: firstValue(searchParams.minStarRating),
		minPrice: firstValue(searchParams.minPrice),
		maxPrice: firstValue(searchParams.maxPrice),
		amenityIds: arrayValue(searchParams.amenityIds),
		roomAmenityIds: arrayValue(searchParams.roomAmenityIds),
		sort: firstValue(searchParams.sort) as CatalogQueryInput["sort"],
		page: firstValue(searchParams.page),
	};
}

/**
 * Builds a /catalog href from the current searchParams plus overrides — an
 * `undefined` override value removes that param. Any change other than an
 * explicit page override resets pagination, since a stale page number can
 * point past the new result set's last page.
 */
export function buildCatalogHref(
	current: CatalogSearchParams,
	overrides: Record<string, string | undefined>,
): string {
	const params = new URLSearchParams();
	for (const [key, value] of Object.entries(current)) {
		if (key in overrides) continue;
		if (Array.isArray(value)) {
			for (const item of value) params.append(key, item);
		} else if (value !== undefined) {
			params.set(key, value);
		}
	}
	for (const [key, value] of Object.entries(overrides)) {
		if (value !== undefined) params.set(key, value);
	}
	if (!("page" in overrides)) params.delete("page");

	const query = params.toString();
	return query ? `/catalog?${query}` : "/catalog";
}
