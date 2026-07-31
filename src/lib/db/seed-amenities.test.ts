import { expect, test } from "vitest";
import { db } from "./client";
import { amenity } from "./schema";
import { seedAmenities } from "./seed-amenities";

test("seedAmenities is idempotent — a second run inserts nothing new", async () => {
	await seedAmenities();
	const firstCount = await db.$count(amenity);

	const second = await seedAmenities();
	const secondCount = await db.$count(amenity);

	expect(second.inserted).toBe(0);
	expect(secondCount).toBe(firstCount);
});

test("seedAmenities covers every taxonomy group used by the intake form", async () => {
	await seedAmenities();
	const rows = await db.select({ group: amenity.group }).from(amenity);
	const groups = new Set(rows.map((row) => row.group));

	for (const expectedGroup of [
		"hotel",
		"room",
		"bathroom",
		"bedroom",
		"general",
	]) {
		expect(groups.has(expectedGroup as (typeof rows)[number]["group"])).toBe(
			true,
		);
	}
});
