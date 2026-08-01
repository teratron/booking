import { and, asc, desc, eq, gt, lt } from "drizzle-orm";
import { db } from "@/lib/db/client";
import {
	amenity,
	hotel,
	reservation,
	room,
	roomAmenity,
	roomMedia,
} from "@/lib/db/schema";

export type RoomDetailAmenity = { id: string; name: string; group: string };

export type RoomDetail = {
	id: string;
	hotelId: string;
	name: string;
	bedConfiguration: string | null;
	guestCapacity: number;
	basePrice: number;
	featureTags: string[];
	photos: string[];
	amenities: RoomDetailAmenity[];
};

/**
 * The room-detail popup's data source (T-6B01). Checks both the room's own
 * moderation status AND its parent hotel's — a room can't be independently
 * reservable if the hotel it belongs to isn't published, mirroring the same
 * belt-and-suspenders moderation check T-5A02's getHotelProfile applies at
 * the hotel level.
 */
export async function getRoomDetail(
	roomId: string,
): Promise<RoomDetail | undefined> {
	const [roomRow] = await db
		.select({
			id: room.id,
			hotelId: room.hotelId,
			name: room.name,
			bedConfiguration: room.bedConfiguration,
			guestCapacity: room.guestCapacity,
			basePrice: room.basePrice,
			featureTags: room.featureTags,
		})
		.from(room)
		.innerJoin(hotel, eq(hotel.id, room.hotelId))
		.where(
			and(
				eq(room.id, roomId),
				eq(room.status, "published"),
				eq(hotel.status, "published"),
			),
		);

	if (!roomRow) return undefined;

	const [photoRows, amenityRows] = await Promise.all([
		db
			.select({ url: roomMedia.url })
			.from(roomMedia)
			.where(eq(roomMedia.roomId, roomId))
			.orderBy(asc(roomMedia.sortOrder)),
		db
			.select({ id: amenity.id, name: amenity.name, group: amenity.group })
			.from(roomAmenity)
			.innerJoin(amenity, eq(amenity.id, roomAmenity.amenityId))
			.where(eq(roomAmenity.roomId, roomId)),
	]);

	return {
		...roomRow,
		basePrice: Number(roomRow.basePrice),
		featureTags: roomRow.featureTags ?? [],
		photos: photoRows.map((row) => row.url),
		amenities: amenityRows,
	};
}

/**
 * l1-room-reservation.md §3: "an unpaid reservation attempt does not hold
 * the dates against availability" — only a `paid` reservation blocks a
 * range, per this phase's own [DR]. `checkIn`/`checkOut` are "YYYY-MM-DD"
 * strings (the `date` column's default string mode); checkout day is
 * exclusive, standard hotel-booking semantics — a guest checking out the
 * same day another checks in is not a conflict.
 */
export async function isRoomAvailable(
	roomId: string,
	checkIn: string,
	checkOut: string,
): Promise<boolean> {
	const overlapping = await db
		.select({ id: reservation.id })
		.from(reservation)
		.where(
			and(
				eq(reservation.roomId, roomId),
				eq(reservation.status, "paid"),
				lt(reservation.checkIn, checkOut),
				gt(reservation.checkOut, checkIn),
			),
		)
		.limit(1);

	return overlapping.length === 0;
}

export type GuestReservation = {
	id: string;
	roomId: string;
	roomName: string;
	hotelId: string;
	hotelName: string;
	checkIn: string;
	checkOut: string;
	guestCount: number;
	status: "pending" | "paid" | "payment_failed" | "cancelled";
	createdAt: Date;
};

/** The guest reservation status page's (T-6D01) data source. */
export async function getGuestReservations(
	guestId: string,
): Promise<GuestReservation[]> {
	return db
		.select({
			id: reservation.id,
			roomId: reservation.roomId,
			roomName: room.name,
			hotelId: hotel.id,
			hotelName: hotel.name,
			checkIn: reservation.checkIn,
			checkOut: reservation.checkOut,
			guestCount: reservation.guestCount,
			status: reservation.status,
			createdAt: reservation.createdAt,
		})
		.from(reservation)
		.innerJoin(room, eq(room.id, reservation.roomId))
		.innerJoin(hotel, eq(hotel.id, room.hotelId))
		.where(eq(reservation.guestId, guestId))
		.orderBy(desc(reservation.createdAt));
}
