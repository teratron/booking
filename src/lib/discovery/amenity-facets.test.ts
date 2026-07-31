import { expect, test } from "vitest";
import { getAmenityFacets } from "@/lib/discovery/amenity-facets";

// The seeded amenity table (seed-amenities.ts) is stable, shared fixture data
// — every "hotel"-group row must land in hotelAmenities, every
// room/bathroom/bedroom/general-group row in roomAmenities, and the two sets
// must be disjoint.
test("splits amenities into hotel-level and room-level facets by group", async () => {
	const facets = await getAmenityFacets();

	expect(facets.hotelAmenities.length).toBeGreaterThan(0);
	expect(facets.roomAmenities.length).toBeGreaterThan(0);

	const hotelIds = new Set(facets.hotelAmenities.map((row) => row.id));
	const overlap = facets.roomAmenities.filter((row) => hotelIds.has(row.id));
	expect(overlap).toEqual([]);

	const hotelNames = facets.hotelAmenities.map((row) => row.name);
	expect(hotelNames).toContain("Wi-Fi");
	const roomNames = facets.roomAmenities.map((row) => row.name);
	expect(roomNames).toContain("Air Conditioning");
});
