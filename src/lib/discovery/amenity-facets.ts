import { asc } from "drizzle-orm";
import { db } from "@/lib/db/client";
import { amenity } from "@/lib/db/schema";

export type AmenityOption = { id: string; name: string };

export type AmenityFacets = {
	hotelAmenities: AmenityOption[];
	roomAmenities: AmenityOption[];
};

const ROOM_LEVEL_GROUPS = new Set(["room", "bathroom", "bedroom", "general"]);

/**
 * Powers the catalog filter sidebar's (T-4C02) amenity checkboxes.
 * l1-hotel-discovery.md §5.2 lists "Amenities (hotel-level)", "Family /
 * children", and "Wellness / treatment" as three separate facets, but the
 * amenity taxonomy (amenityGroupEnum: hotel/room/bathroom/bedroom/general —
 * see db/schema.ts) has no data backing that three-way split; every seeded
 * hotel-level amenity is just "hotel"-grouped. Rather than invent an
 * undocumented name-keyword heuristic to fake three sections, all
 * "hotel"-group amenities render under one "Amenities" section — matching
 * catalog-query.ts's own amenityIds doc comment, which already treats those
 * three spec facets as one query dimension. "Room facilities" is the one
 * facet genuinely backed by a distinct data dimension (roomAmenityIds vs
 * amenityIds — room/bathroom/bedroom/general-group amenities, matched
 * against a hotel's published rooms) and gets its own section.
 */
export async function getAmenityFacets(): Promise<AmenityFacets> {
	const rows = await db
		.select({ id: amenity.id, name: amenity.name, group: amenity.group })
		.from(amenity)
		.orderBy(asc(amenity.name));

	return {
		hotelAmenities: rows.filter((row) => row.group === "hotel"),
		roomAmenities: rows.filter((row) => ROOM_LEVEL_GROUPS.has(row.group)),
	};
}
