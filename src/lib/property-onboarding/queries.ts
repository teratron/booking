import { asc, desc, eq } from "drizzle-orm";
import { db } from "@/lib/db/client";
import {
	hotel,
	hotelAmenity,
	hotelMedia,
	room,
	roomAmenity,
	roomMedia,
} from "@/lib/db/schema";
import type { HotelListingInput } from "./schema";

// Server-only — the owner dashboard is a Server Component, so this never
// needs the schema/actions split T-3B02 required for Client Component use.
export async function getOwnerListings(ownerId: string) {
	return db
		.select({
			id: hotel.id,
			name: hotel.name,
			status: hotel.status,
			moderationReason: hotel.moderationReason,
			createdAt: hotel.createdAt,
		})
		.from(hotel)
		.where(eq(hotel.ownerId, ownerId))
		.orderBy(desc(hotel.createdAt));
}

/**
 * Shapes a hotel and its rooms/amenities/media into the same
 * HotelListingInput the intake form and Server Actions use, so the edit page
 * (T-3C02) can pre-fill the shared form component directly. Returns null if
 * the hotel doesn't exist — ownership/status gating is the caller's job
 * (page-level, since it also decides the 403 vs 404 response).
 */
export async function getHotelForEdit(hotelId: string) {
	const [hotelRow] = await db.select().from(hotel).where(eq(hotel.id, hotelId));
	if (!hotelRow) return null;

	const [hotelAmenityRows, hotelMediaRows, roomRows] = await Promise.all([
		db
			.select({ amenityId: hotelAmenity.amenityId })
			.from(hotelAmenity)
			.where(eq(hotelAmenity.hotelId, hotelId)),
		db
			.select({ url: hotelMedia.url, type: hotelMedia.type })
			.from(hotelMedia)
			.where(eq(hotelMedia.hotelId, hotelId))
			.orderBy(asc(hotelMedia.sortOrder)),
		db.select().from(room).where(eq(room.hotelId, hotelId)),
	]);

	const rooms = await Promise.all(
		roomRows.map(
			async (roomRow): Promise<HotelListingInput["rooms"][number]> => {
				const [amenityRows, mediaRows] = await Promise.all([
					db
						.select({ amenityId: roomAmenity.amenityId })
						.from(roomAmenity)
						.where(eq(roomAmenity.roomId, roomRow.id)),
					db
						.select({ url: roomMedia.url })
						.from(roomMedia)
						.where(eq(roomMedia.roomId, roomRow.id))
						.orderBy(asc(roomMedia.sortOrder)),
				]);
				return {
					name: roomRow.name,
					bedConfiguration: roomRow.bedConfiguration ?? undefined,
					guestCapacity: roomRow.guestCapacity,
					basePrice: Number(roomRow.basePrice),
					featureTags: roomRow.featureTags ?? undefined,
					amenityIds: amenityRows.map((r) => r.amenityId),
					mediaUrls: mediaRows.map((r) => r.url),
				};
			},
		),
	);

	const formValues: HotelListingInput = {
		name: hotelRow.name,
		accommodationType: hotelRow.accommodationType,
		starCategory: hotelRow.starCategory ?? undefined,
		address: hotelRow.address,
		latitude: hotelRow.latitude,
		longitude: hotelRow.longitude,
		phone: hotelRow.phone,
		amenityIds: hotelAmenityRows.map((r) => r.amenityId),
		media: hotelMediaRows,
		rooms,
	};

	return { hotel: hotelRow, formValues };
}
