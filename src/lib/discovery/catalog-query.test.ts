import { eq } from "drizzle-orm";
import { afterAll, beforeAll, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import {
	amenity,
	hotel,
	hotelAmenity,
	review,
	room,
	user,
} from "@/lib/db/schema";
import { getCatalogMapPins, getCatalogResults } from "./catalog-query";

const testHotelIds: string[] = [];
let ownerId: string;
let testAmenityId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-catalog-owner",
			name: "Catalog Owner",
			email: "catalog-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "Catalog Owner" } })
		.returning();
	ownerId = owner.id;

	const [amenityRow] = await db
		.select({ id: amenity.id })
		.from(amenity)
		.limit(1);
	testAmenityId = amenityRow.id;

	async function insertHotel(overrides: Partial<typeof hotel.$inferInsert>) {
		const [row] = await db
			.insert(hotel)
			.values({
				ownerId,
				name: "Fixture Hotel",
				address: "Fixture Address",
				latitude: 1,
				longitude: 1,
				phone: "+10000000000",
				status: "published",
				...overrides,
			})
			.returning();
		testHotelIds.push(row.id);
		return row;
	}

	const grandPalace = await insertHotel({
		name: "Grand Palace",
		address: "1 Kyiv St, Kyiv",
		accommodationType: "hotel",
		starCategory: 5,
	});
	await db.insert(room).values({
		hotelId: grandPalace.id,
		name: "Suite",
		guestCapacity: 2,
		basePrice: "200.00",
		status: "published",
	});
	await db
		.insert(hotelAmenity)
		.values({ hotelId: grandPalace.id, amenityId: testAmenityId });
	await db.insert(review).values([
		{
			hotelId: grandPalace.id,
			guestId: ownerId,
			rating: 5,
			comment: "Great",
			status: "published",
		},
		{
			hotelId: grandPalace.id,
			guestId: ownerId,
			rating: 5,
			comment: "Great again",
			status: "published",
		},
	]);

	const cozyHostel = await insertHotel({
		name: "Cozy Hostel",
		address: "2 Lviv St, Lviv",
		accommodationType: "hostel",
		starCategory: 2,
	});
	await db.insert(room).values({
		hotelId: cozyHostel.id,
		name: "Dorm Bed",
		guestCapacity: 1,
		basePrice: "30.00",
		status: "published",
	});
	await db.insert(review).values({
		hotelId: cozyHostel.id,
		guestId: ownerId,
		rating: 3,
		comment: "Ok",
		status: "published",
	});

	// Neither of these should ever appear in a catalog result, at any filter.
	await insertHotel({
		name: "Pending Place",
		address: "3 Kyiv St, Kyiv",
		status: "pending",
	});
	await insertHotel({ name: "Rejected Place", status: "rejected" });
});

afterAll(async () => {
	for (const id of testHotelIds) {
		await db.delete(hotel).where(eq(hotel.id, id));
	}
	await db.delete(user).where(eq(user.id, ownerId));
});

test("only published hotels are ever returned", async () => {
	const page = await getCatalogResults({ sort: "popularity", page: 1 });
	const names = page.results.map((r) => r.name);
	expect(names).not.toContain("Pending Place");
	expect(names).not.toContain("Rejected Place");
});

test("destination narrows by address substring, excluding a matching-but-unpublished hotel", async () => {
	const page = await getCatalogResults({
		destination: "Kyiv",
		sort: "popularity",
		page: 1,
	});
	expect(page.results.map((r) => r.name)).toEqual(["Grand Palace"]);
});

test("accommodationType narrows to an exact match", async () => {
	const page = await getCatalogResults({
		accommodationType: "hostel",
		sort: "popularity",
		page: 1,
	});
	expect(page.results.map((r) => r.name)).toEqual(["Cozy Hostel"]);
});

test("minStarRating is a floor, not an exact match", async () => {
	const page = await getCatalogResults({
		minStarRating: 4,
		sort: "popularity",
		page: 1,
	});
	expect(page.results.map((r) => r.name)).toEqual(["Grand Palace"]);
});

test("price range narrows by the hotel's cheapest published room", async () => {
	const expensive = await getCatalogResults({ minPrice: 100, page: 1 });
	expect(expensive.results.map((r) => r.name)).toEqual(["Grand Palace"]);

	const cheap = await getCatalogResults({ maxPrice: 50, page: 1 });
	expect(cheap.results.map((r) => r.name)).toEqual(["Cozy Hostel"]);
});

test("amenityIds narrows to hotels carrying that amenity", async () => {
	// destination-scoped for the same reason "price range narrows..." above
	// is: testAmenityId is picked via an unordered query with no fixture-
	// specific tag, so any other file's hotel that happens to carry the same
	// amenity id (a real, now-observed collision — see catalog-contract.test.ts,
	// T-4T01) would otherwise leak into this exact-equality assertion.
	const page = await getCatalogResults({
		destination: "Kyiv",
		amenityIds: [testAmenityId],
		page: 1,
	});
	expect(page.results.map((r) => r.name)).toEqual(["Grand Palace"]);
});

test("sort by price is ascending; sort by popularity favors more reviews", async () => {
	const byPrice = await getCatalogResults({ sort: "price", page: 1 });
	const pricedNames = byPrice.results
		.filter((r) => ["Grand Palace", "Cozy Hostel"].includes(r.name))
		.map((r) => r.name);
	expect(pricedNames.indexOf("Cozy Hostel")).toBeLessThan(
		pricedNames.indexOf("Grand Palace"),
	);

	const byPopularity = await getCatalogResults({ sort: "popularity", page: 1 });
	const popularNames = byPopularity.results
		.filter((r) => ["Grand Palace", "Cozy Hostel"].includes(r.name))
		.map((r) => r.name);
	expect(popularNames.indexOf("Grand Palace")).toBeLessThan(
		popularNames.indexOf("Cozy Hostel"),
	);
});

test("result cards carry aggregate rating, review count, and starting price", async () => {
	const page = await getCatalogResults({
		destination: "Kyiv",
		sort: "popularity",
		page: 1,
	});
	const grandPalace = page.results[0];
	expect(grandPalace.avgRating).toBe(5);
	expect(grandPalace.reviewCount).toBe(2);
	expect(grandPalace.startingPrice).toBe(200);
	expect(grandPalace.amenityBadges.length).toBeGreaterThan(0);
});

test("pagination reports a total independent of the current page's row count", async () => {
	const page = await getCatalogResults({ sort: "popularity", page: 1 });
	expect(page.total).toBeGreaterThanOrEqual(2);
	expect(page.pageSize).toBe(12);
	expect(page.page).toBe(1);
});

test("map pins use the same filter as getCatalogResults but carry no page limit", async () => {
	const filtered = await getCatalogMapPins({ destination: "Kyiv" });
	expect(filtered.map((pin) => pin.name)).toEqual(["Grand Palace"]);
	expect(filtered[0].latitude).toBe(1);
	expect(filtered[0].longitude).toBe(1);

	const unfiltered = await getCatalogMapPins({});
	const names = unfiltered.map((pin) => pin.name);
	expect(names).toContain("Grand Palace");
	expect(names).toContain("Cozy Hostel");
	expect(names).not.toContain("Pending Place");
	expect(names).not.toContain("Rejected Place");
});
