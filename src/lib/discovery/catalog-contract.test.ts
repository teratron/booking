import { desc, eq } from "drizzle-orm";
import { afterAll, beforeAll, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import { amenity, hotel, hotelAmenity, room, user } from "@/lib/db/schema";
import { getCatalogResults, PAGE_SIZE } from "./catalog-query";
import {
	type CatalogSearchParams,
	parseCatalogQueryInput,
} from "./catalog-url";

// Phase 4 exit gate (T-4T01) — mirrors T-2T01/T-3T01's role. Unlike
// catalog-query.test.ts (T-4A01, tests getCatalogResults directly with
// already-typed params) and catalog-url.test.ts (T-4C01, tests
// parseCatalogQueryInput in isolation), this file proves the two actually
// compose: a raw URL querystring parses into the exact query getCatalogResults
// needs, and produces the exact same result set as calling it directly.
// Destination is a unique per-file token ("T4T01City") specifically so an
// unfiltered/loosely-scoped assertion elsewhere in the parallel-worker suite
// can never observe this fixture, and vice versa — see STATE.md's Blocking
// Constraint on cross-file Postgres fixture visibility (discovered T-4C01).
const DESTINATION = "T4T01City";
const testHotelIds: string[] = [];
let ownerId: string;
let testAmenityId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-t4t01-owner",
			name: "T4T01 Owner",
			email: "t4t01-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T4T01 Owner" } })
		.returning();
	ownerId = owner.id;

	// catalog-query.test.ts (T-4A01) picks its own testAmenityId via an
	// unordered `.limit(1)` on this same table — since that resolves
	// deterministically to the same physical row, picking "the last row by
	// name" here instead guarantees a *different* amenity, so this file's
	// globally-visible hotels can never leak into that file's unscoped
	// `amenityIds` assertion. See STATE.md's Blocking Constraint on cross-file
	// Postgres fixture visibility — this collision is exactly that risk,
	// manifesting between two amenity id picks rather than two hotel fixtures.
	const [amenityRow] = await db
		.select({ id: amenity.id })
		.from(amenity)
		.orderBy(desc(amenity.name))
		.limit(1);
	testAmenityId = amenityRow.id;

	async function insertQualifyingHotel(index: number, overrides = {}) {
		const [row] = await db
			.insert(hotel)
			.values({
				ownerId,
				name: `T4T01 Hotel ${index}`,
				address: `${index} Fixture Ave, ${DESTINATION}`,
				latitude: 1,
				longitude: 1,
				phone: "+10000000000",
				accommodationType: "hotel",
				starCategory: 4,
				status: "published",
				...overrides,
			})
			.returning();
		testHotelIds.push(row.id);
		await db.insert(room).values({
			hotelId: row.id,
			name: "Standard",
			guestCapacity: 2,
			// Base 500, not 100: catalog-query.test.ts's own fixtures span
			// 30–200 (Cozy Hostel–Grand Palace) — a price band that overlaps
			// theirs would interleave into their unfiltered price-ascending
			// sort assertion and crowd Grand Palace off page 1 (a real
			// collision, observed live). Staying clear of [0, 200] avoids it
			// regardless of how many hotels this fixture ever grows to.
			basePrice: `${500 + index}.00`,
			status: "published",
		});
		await db
			.insert(hotelAmenity)
			.values({ hotelId: row.id, amenityId: testAmenityId });
		return row;
	}

	// 13 qualifying hotels: exceeds PAGE_SIZE (12), forcing a genuine page-2
	// round-trip rather than a single-page test that can't distinguish
	// "pagination survived the round-trip" from "there was only one page".
	for (let index = 0; index < 13; index += 1) {
		await insertQualifyingHotel(index);
	}

	// Matches every filter (destination/accommodationType/minStarRating/
	// amenityIds) but not published — must never appear in either page.
	await insertQualifyingHotel(99, {
		name: "T4T01 Pending Hotel",
		status: "pending",
	});
});

afterAll(async () => {
	for (const id of testHotelIds) {
		await db.delete(hotel).where(eq(hotel.id, id));
	}
	await db.delete(user).where(eq(user.id, ownerId));
});

test("Home's default list and Catalog's default URL state resolve to the identical query and result set", async () => {
	// (marketing)/page.tsx's own literal call — Home is not an independent
	// editorial feed, per this phase's own [DR] (tasks/phase-4.md Decisions).
	const homeQuery = { sort: "popularity" as const, page: 1 };
	// What parseCatalogQueryInput produces for a bare `/catalog` request.
	const catalogQuery = parseCatalogQueryInput({});

	const [homePage, catalogPage] = await Promise.all([
		getCatalogResults(homeQuery),
		getCatalogResults(catalogQuery),
	]);

	expect(catalogPage.results.map((r) => r.id)).toEqual(
		homePage.results.map((r) => r.id),
	);
	expect(catalogPage.total).toBe(homePage.total);
});

test("a filtered + sorted catalog URL round-trips to the exact same query and page-1 result set", async () => {
	const directPage = await getCatalogResults({
		destination: DESTINATION,
		accommodationType: "hotel",
		minStarRating: 4,
		amenityIds: [testAmenityId],
		sort: "price",
		page: 1,
	});
	expect(directPage.total).toBe(13);
	expect(directPage.results).toHaveLength(PAGE_SIZE);
	expect(directPage.results.map((r) => r.name)).not.toContain(
		"T4T01 Pending Hotel",
	);

	// The exact shape a browser produces for a URL with these params — the
	// shareable-URL invariant (l1-hotel-discovery.md §2) is that this raw
	// object, not a hand-typed CatalogQueryInput, drives the page.
	const rawSearchParams: CatalogSearchParams = {
		destination: DESTINATION,
		accommodationType: "hotel",
		minStarRating: "4",
		amenityIds: [testAmenityId],
		sort: "price",
		page: "1",
	};
	const reparsedPage = await getCatalogResults(
		parseCatalogQueryInput(rawSearchParams),
	);

	expect(reparsedPage.results.map((r) => r.id)).toEqual(
		directPage.results.map((r) => r.id),
	);
});

test("the same round-trip on page 2 returns the remaining hotel, still excluding the pending one", async () => {
	const directPage = await getCatalogResults({
		destination: DESTINATION,
		accommodationType: "hotel",
		minStarRating: 4,
		amenityIds: [testAmenityId],
		sort: "price",
		page: 2,
	});
	expect(directPage.results).toHaveLength(13 - PAGE_SIZE);

	const rawSearchParams: CatalogSearchParams = {
		destination: DESTINATION,
		accommodationType: "hotel",
		minStarRating: "4",
		amenityIds: [testAmenityId],
		sort: "price",
		page: "2",
	};
	const reparsedPage = await getCatalogResults(
		parseCatalogQueryInput(rawSearchParams),
	);

	expect(reparsedPage.results.map((r) => r.id)).toEqual(
		directPage.results.map((r) => r.id),
	);
	expect(reparsedPage.results.map((r) => r.name)).not.toContain(
		"T4T01 Pending Hotel",
	);
});
