import "dotenv/config";
import { db, pool } from "./client";
import { amenity } from "./schema";

// Representative, not exhaustive, entries per group (l1-property-onboarding.md
// §5.1 groups hotel amenities separately from room/bathroom/bedroom/general —
// the same taxonomy l1-room-reservation.md §3 uses for the room detail popup).
const AMENITIES: Array<{
	name: string;
	group: (typeof amenity.$inferInsert)["group"];
	icon: string;
}> = [
	{ name: "Wi-Fi", group: "hotel", icon: "wifi" },
	{ name: "Parking", group: "hotel", icon: "car" },
	{ name: "Pool", group: "hotel", icon: "waves" },
	{ name: "Restaurant", group: "hotel", icon: "utensils" },
	{ name: "Fitness Center", group: "hotel", icon: "dumbbell" },
	{ name: "Spa", group: "hotel", icon: "sparkles" },
	{ name: "Airport Shuttle", group: "hotel", icon: "bus" },
	{ name: "Pet Friendly", group: "hotel", icon: "paw-print" },
	{ name: "Air Conditioning", group: "room", icon: "wind" },
	{ name: "Balcony", group: "room", icon: "door-open" },
	{ name: "Sea View", group: "room", icon: "eye" },
	{ name: "Mini Bar", group: "room", icon: "wine" },
	{ name: "Safe", group: "room", icon: "lock" },
	{ name: "Desk", group: "room", icon: "briefcase" },
	{ name: "Private Bathroom", group: "bathroom", icon: "bath" },
	{ name: "Bathtub", group: "bathroom", icon: "bath" },
	{ name: "Shower", group: "bathroom", icon: "shower-head" },
	{ name: "Hairdryer", group: "bathroom", icon: "wind" },
	{ name: "Toiletries", group: "bathroom", icon: "droplet" },
	{ name: "Double Bed", group: "bedroom", icon: "bed-double" },
	{ name: "Twin Beds", group: "bedroom", icon: "bed-single" },
	{ name: "Wardrobe", group: "bedroom", icon: "shirt" },
	{ name: "Blackout Curtains", group: "bedroom", icon: "moon" },
	{ name: "Non-Smoking", group: "general", icon: "cigarette-off" },
	{ name: "Soundproofing", group: "general", icon: "volume-x" },
	{ name: "Wheelchair Accessible", group: "general", icon: "accessibility" },
	{ name: "Daily Housekeeping", group: "general", icon: "sparkle" },
];

export async function seedAmenities() {
	const existing = await db.select({ name: amenity.name }).from(amenity);
	const existingNames = new Set(existing.map((row) => row.name));

	const toInsert = AMENITIES.filter((entry) => !existingNames.has(entry.name));
	if (toInsert.length > 0) {
		await db.insert(amenity).values(toInsert);
	}

	return {
		inserted: toInsert.length,
		skipped: AMENITIES.length - toInsert.length,
	};
}

async function main() {
	const result = await seedAmenities();
	console.log(
		`Amenity seed: ${result.inserted} inserted, ${result.skipped} already present.`,
	);
	await pool.end();
}

if (require.main === module) {
	main().catch((error) => {
		console.error(error);
		process.exitCode = 1;
	});
}
