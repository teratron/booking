import { and, asc, desc, eq, inArray } from "drizzle-orm";
import { db } from "@/lib/db/client";
import {
	amenity,
	hotel,
	hotelAmenity,
	hotelMedia,
	review,
	room,
	roomMedia,
	user,
} from "@/lib/db/schema";

export type HotelProfileRoom = {
	id: string;
	name: string;
	guestCapacity: number;
	basePrice: number;
	coverPhotoUrl: string | null;
};

export type HotelProfileReview = {
	id: string;
	guestName: string;
	guestAvatar: string | null;
	rating: number;
	comment: string;
	createdAt: Date;
};

export type HotelProfileAmenity = { id: string; name: string; group: string };

export type HotelProfile = {
	id: string;
	name: string;
	address: string;
	latitude: number;
	longitude: number;
	phone: string;
	accommodationType: string;
	starCategory: number | null;
	galleryPhotos: string[];
	amenities: HotelProfileAmenity[];
	rooms: HotelProfileRoom[];
	avgRating: number | null;
	reviewCount: number;
	reviews: HotelProfileReview[];
};

/**
 * The full aggregation the hotel profile page (T-5B01/T-5B02) renders in one
 * shot. Returns `undefined` for a `pending`/`rejected`/missing hotel — the
 * same moderation checkpoint T-4A01's catalog query enforces, applied here to
 * a single-hotel lookup instead of a filtered list. Every array field
 * defaults to `[]` rather than being omitted, so a hotel with zero gallery
 * photos/amenities/rooms/reviews still returns a well-formed shape — the
 * data-layer half of l1-hotel-profile.md §3's "every section degrades
 * independently" invariant (T-5B01/B02 own the render-layer half: no section
 * may be a hard dependency for page render).
 */
export async function getHotelProfile(
	hotelId: string,
): Promise<HotelProfile | undefined> {
	const [hotelRow] = await db
		.select({
			id: hotel.id,
			name: hotel.name,
			address: hotel.address,
			latitude: hotel.latitude,
			longitude: hotel.longitude,
			phone: hotel.phone,
			accommodationType: hotel.accommodationType,
			starCategory: hotel.starCategory,
		})
		.from(hotel)
		.where(and(eq(hotel.id, hotelId), eq(hotel.status, "published")));

	if (!hotelRow) return undefined;

	const [galleryRows, amenityRows, roomRows, reviewRows] = await Promise.all([
		db
			.select({ url: hotelMedia.url })
			.from(hotelMedia)
			.where(and(eq(hotelMedia.hotelId, hotelId), eq(hotelMedia.type, "photo")))
			.orderBy(asc(hotelMedia.sortOrder)),
		db
			.select({ id: amenity.id, name: amenity.name, group: amenity.group })
			.from(hotelAmenity)
			.innerJoin(amenity, eq(amenity.id, hotelAmenity.amenityId))
			.where(eq(hotelAmenity.hotelId, hotelId)),
		db
			.select({
				id: room.id,
				name: room.name,
				guestCapacity: room.guestCapacity,
				basePrice: room.basePrice,
			})
			.from(room)
			.where(and(eq(room.hotelId, hotelId), eq(room.status, "published"))),
		db
			.select({
				id: review.id,
				rating: review.rating,
				comment: review.comment,
				createdAt: review.createdAt,
				guestName: user.name,
				guestAvatar: user.image,
			})
			.from(review)
			.innerJoin(user, eq(user.id, review.guestId))
			.where(and(eq(review.hotelId, hotelId), eq(review.status, "published")))
			.orderBy(desc(review.createdAt)),
	]);

	const roomIds = roomRows.map((row) => row.id);
	const roomCoverPhotos =
		roomIds.length === 0
			? []
			: await db
					.select({ roomId: roomMedia.roomId, url: roomMedia.url })
					.from(roomMedia)
					.where(inArray(roomMedia.roomId, roomIds))
					.orderBy(asc(roomMedia.sortOrder));

	const coverByRoom = new Map<string, string>();
	for (const row of roomCoverPhotos) {
		if (!coverByRoom.has(row.roomId)) coverByRoom.set(row.roomId, row.url);
	}

	const reviewCount = reviewRows.length;
	const avgRating =
		reviewCount === 0
			? null
			: reviewRows.reduce((sum, r) => sum + r.rating, 0) / reviewCount;

	return {
		...hotelRow,
		galleryPhotos: galleryRows.map((row) => row.url),
		amenities: amenityRows,
		rooms: roomRows.map((row) => ({
			...row,
			basePrice: Number(row.basePrice),
			coverPhotoUrl: coverByRoom.get(row.id) ?? null,
		})),
		avgRating,
		reviewCount,
		reviews: reviewRows,
	};
}
