import { and, asc, desc, eq, gte, ilike, inArray, lte, sql } from "drizzle-orm";
import { z } from "zod";
import { db } from "@/lib/db/client";
import {
	accommodationTypeEnum,
	amenity,
	hotel,
	hotelAmenity,
	hotelMedia,
	review,
	room,
	roomAmenity,
} from "@/lib/db/schema";

export const PAGE_SIZE = 12;

// Within one facet, multiple selected values are OR'd (has any of these
// amenities); across facets, conditions are AND'd. Matches conventional
// catalog-filter UX, not an exact-match-all-amenities requirement.
const catalogQuerySchema = z.object({
	destination: z.string().trim().min(1).optional(),
	accommodationType: z.enum(accommodationTypeEnum.enumValues).optional(),
	minStarRating: z.coerce.number().int().min(1).max(5).optional(),
	minPrice: z.coerce.number().nonnegative().optional(),
	maxPrice: z.coerce.number().nonnegative().optional(),
	// Covers l1-hotel-discovery.md's "Amenities (hotel-level)", "Family /
	// children", and "Wellness / treatment" facets uniformly — all three are
	// just "hotel has this amenity"; the filter sidebar (T-4C02) groups
	// amenity rows into those three labeled sections, this module doesn't
	// need to know which section a given amenity id came from.
	amenityIds: z.array(z.string().uuid()).optional(),
	// l1-hotel-discovery.md's "Room facilities" facet — hotel qualifies if
	// it has at least one published room carrying any of these amenities.
	roomAmenityIds: z.array(z.string().uuid()).optional(),
	sort: z.enum(["price", "popularity"]).default("popularity"),
	page: z.coerce.number().int().min(1).default(1),
});

type CatalogQuery = z.infer<typeof catalogQuerySchema>;
// getCatalogResults's own parameter type — `sort`/`page` carry `.default()`s,
// so z.infer (the post-parse output type) would wrongly require callers to
// pass them explicitly. z.input is the pre-parse shape, where defaulted
// fields are genuinely optional.
export type CatalogQueryInput = z.input<typeof catalogQuerySchema>;

export type CatalogResult = {
	id: string;
	name: string;
	address: string;
	latitude: number;
	longitude: number;
	accommodationType: string;
	starCategory: number | null;
	coverPhotoUrl: string | null;
	startingPrice: number | null;
	avgRating: number | null;
	reviewCount: number;
	amenityBadges: string[];
};

export type CatalogPage = {
	results: CatalogResult[];
	total: number;
	page: number;
	pageSize: number;
};

function destinationCondition(query: CatalogQuery) {
	if (!query.destination) return undefined;
	return ilike(hotel.address, `%${query.destination}%`);
}

function accommodationTypeCondition(query: CatalogQuery) {
	if (!query.accommodationType) return undefined;
	return eq(hotel.accommodationType, query.accommodationType);
}

function minStarRatingCondition(query: CatalogQuery) {
	if (!query.minStarRating) return undefined;
	return gte(hotel.starCategory, query.minStarRating);
}

function amenityIdsCondition(query: CatalogQuery) {
	if (!query.amenityIds || query.amenityIds.length === 0) return undefined;
	return inArray(
		hotel.id,
		db
			.selectDistinct({ id: hotelAmenity.hotelId })
			.from(hotelAmenity)
			.where(inArray(hotelAmenity.amenityId, query.amenityIds)),
	);
}

function roomAmenityIdsCondition(query: CatalogQuery) {
	if (!query.roomAmenityIds || query.roomAmenityIds.length === 0) {
		return undefined;
	}
	return inArray(
		hotel.id,
		db
			.selectDistinct({ id: room.hotelId })
			.from(room)
			.innerJoin(roomAmenity, eq(roomAmenity.roomId, room.id))
			.where(
				and(
					eq(room.status, "published"),
					inArray(roomAmenity.amenityId, query.roomAmenityIds),
				),
			),
	);
}

function priceRangeCondition(query: CatalogQuery) {
	if (query.minPrice === undefined && query.maxPrice === undefined) {
		return undefined;
	}
	const priceConditions = [eq(room.status, "published")];
	if (query.minPrice !== undefined) {
		priceConditions.push(gte(room.basePrice, query.minPrice.toFixed(2)));
	}
	if (query.maxPrice !== undefined) {
		priceConditions.push(lte(room.basePrice, query.maxPrice.toFixed(2)));
	}
	return inArray(
		hotel.id,
		db
			.selectDistinct({ id: room.hotelId })
			.from(room)
			.where(and(...priceConditions)),
	);
}

function buildConditions(query: CatalogQuery) {
	const conditions = [
		eq(hotel.status, "published"),
		destinationCondition(query),
		accommodationTypeCondition(query),
		minStarRatingCondition(query),
		amenityIdsCondition(query),
		roomAmenityIdsCondition(query),
		priceRangeCondition(query),
	].filter((condition) => condition !== undefined);

	return and(...conditions);
}

/**
 * The shared filter/sort/paginate contract l1-hotel-discovery.md §6 requires
 * Home and Catalog to use identically — Home calls this with default params
 * (no filters, sort: "popularity", page: 1), Catalog calls it with the
 * sidebar/hero-search-derived params. Only `status: "published"` hotels are
 * ever returned, enforcing the moderation-checkpoint invariant at the query
 * layer regardless of which route calls it.
 */
export async function getCatalogResults(
	rawQuery: CatalogQueryInput,
): Promise<CatalogPage> {
	const query = catalogQuerySchema.parse(rawQuery);
	const where = buildConditions(query);

	const [{ total }] = await db
		.select({ total: sql<number>`count(*)::int` })
		.from(hotel)
		.where(where);

	const sortColumn =
		query.sort === "price"
			? sql`(select min(${room.basePrice}) from ${room} where ${room.hotelId} = ${hotel.id} and ${room.status} = 'published')`
			: sql`(select count(*) from ${review} where ${review.hotelId} = ${hotel.id} and ${review.status} = 'published')`;
	const orderDirection = query.sort === "price" ? asc : desc;

	const idRows = await db
		.select({ id: hotel.id })
		.from(hotel)
		.where(where)
		.orderBy(orderDirection(sortColumn), desc(hotel.createdAt))
		.limit(PAGE_SIZE)
		.offset((query.page - 1) * PAGE_SIZE);

	const hotelIds = idRows.map((row) => row.id);
	if (hotelIds.length === 0) {
		return { results: [], total, page: query.page, pageSize: PAGE_SIZE };
	}

	const results = await getCatalogResultCards(hotelIds);
	const order = new Map(hotelIds.map((id, index) => [id, index]));
	results.sort((a, b) => (order.get(a.id) ?? 0) - (order.get(b.id) ?? 0));

	return { results, total, page: query.page, pageSize: PAGE_SIZE };
}

export type CatalogMapPin = {
	id: string;
	name: string;
	latitude: number;
	longitude: number;
};

/**
 * All coordinates matching the current filter, unpaginated. Per
 * l1-hotel-discovery.md's map invariant ("synchronized with the filtered
 * result set") and this phase's own [DR] (tasks/phase-4.md Decisions), the
 * map plots every filtered hotel, not just the current page — showing only
 * a page's worth while claiming to reflect "the filtered result set" would
 * misrepresent what's actually available at that filter combination. Reuses
 * buildConditions so this stays byte-for-byte the same filter semantics as
 * getCatalogResults, just without the pagination limit/offset.
 */
export async function getCatalogMapPins(
	rawQuery: CatalogQueryInput,
): Promise<CatalogMapPin[]> {
	const query = catalogQuerySchema.parse(rawQuery);
	const where = buildConditions(query);

	return db
		.select({
			id: hotel.id,
			name: hotel.name,
			latitude: hotel.latitude,
			longitude: hotel.longitude,
		})
		.from(hotel)
		.where(where);
}

/**
 * Fetches full result-card data for a fixed set of hotel ids. Split from
 * getCatalogResults so a caller with an already-known id set (e.g. a future
 * "similar hotels" or "recently viewed" feature) can reuse this card-shaping
 * logic without re-running the filter/sort/paginate query. The map view
 * (T-4C03) ended up not needing it — pins only need id/name/coordinates, not
 * full card data — so getCatalogMapPins queries those directly instead.
 */
async function getCatalogResultCards(
	hotelIds: string[],
): Promise<CatalogResult[]> {
	if (hotelIds.length === 0) return [];

	const [rows, coverPhotos, prices, ratings, amenityNames] = await Promise.all([
		db
			.select({
				id: hotel.id,
				name: hotel.name,
				address: hotel.address,
				latitude: hotel.latitude,
				longitude: hotel.longitude,
				accommodationType: hotel.accommodationType,
				starCategory: hotel.starCategory,
			})
			.from(hotel)
			.where(inArray(hotel.id, hotelIds)),
		db
			.select({ hotelId: hotelMedia.hotelId, url: hotelMedia.url })
			.from(hotelMedia)
			.where(
				and(
					inArray(hotelMedia.hotelId, hotelIds),
					eq(hotelMedia.type, "photo"),
				),
			)
			.orderBy(asc(hotelMedia.sortOrder)),
		db
			.select({
				hotelId: room.hotelId,
				minPrice: sql<string>`min(${room.basePrice})`,
			})
			.from(room)
			.where(and(inArray(room.hotelId, hotelIds), eq(room.status, "published")))
			.groupBy(room.hotelId),
		db
			.select({
				hotelId: review.hotelId,
				avgRating: sql<string>`avg(${review.rating})`,
				reviewCount: sql<number>`count(*)::int`,
			})
			.from(review)
			.where(
				and(inArray(review.hotelId, hotelIds), eq(review.status, "published")),
			)
			.groupBy(review.hotelId),
		db
			.select({ hotelId: hotelAmenity.hotelId, name: amenity.name })
			.from(hotelAmenity)
			.innerJoin(amenity, eq(amenity.id, hotelAmenity.amenityId))
			.where(inArray(hotelAmenity.hotelId, hotelIds)),
	]);

	const coverByHotel = new Map<string, string>();
	for (const row of coverPhotos) {
		if (!coverByHotel.has(row.hotelId)) coverByHotel.set(row.hotelId, row.url);
	}
	const priceByHotel = new Map(
		prices.map((p) => [p.hotelId, Number(p.minPrice)]),
	);
	const ratingByHotel = new Map(
		ratings.map((r) => [
			r.hotelId,
			{ avg: Number(r.avgRating), count: r.reviewCount },
		]),
	);
	const badgesByHotel = new Map<string, string[]>();
	for (const row of amenityNames) {
		const existing = badgesByHotel.get(row.hotelId) ?? [];
		if (existing.length < 3) existing.push(row.name);
		badgesByHotel.set(row.hotelId, existing);
	}

	return rows.map((row) => ({
		...row,
		coverPhotoUrl: coverByHotel.get(row.id) ?? null,
		startingPrice: priceByHotel.get(row.id) ?? null,
		avgRating: ratingByHotel.get(row.id)?.avg ?? null,
		reviewCount: ratingByHotel.get(row.id)?.count ?? 0,
		amenityBadges: badgesByHotel.get(row.id) ?? [],
	}));
}
