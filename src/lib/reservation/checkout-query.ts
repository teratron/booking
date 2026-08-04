import { eq } from "drizzle-orm";
import { db } from "@/lib/db/client";
import { hotel, reservation, room } from "@/lib/db/schema";
import { calculateNightsAndAmount } from "./pricing";

export type ReservationCheckoutDetail = {
	id: string;
	guestId: string;
	hotelName: string;
	roomName: string;
	checkIn: string;
	checkOut: string;
	guestCount: number;
	status: "pending" | "paid" | "payment_failed" | "cancelled";
	nights: number;
	amount: number;
};

/**
 * The checkout page's display data source. Joins a single reservation to its
 * room and hotel (for the guest-facing summary) and derives the same
 * nights/amount figure `initiatePaymentCore` computes internally — recomputed
 * here purely for display, this function does not persist anything, so it is
 * safe to call before ownership/status have been checked by the caller.
 */
export async function getReservationForCheckout(
	reservationId: string,
): Promise<ReservationCheckoutDetail | undefined> {
	const [row] = await db
		.select({
			id: reservation.id,
			guestId: reservation.guestId,
			hotelName: hotel.name,
			roomName: room.name,
			checkIn: reservation.checkIn,
			checkOut: reservation.checkOut,
			guestCount: reservation.guestCount,
			status: reservation.status,
			basePrice: room.basePrice,
		})
		.from(reservation)
		.innerJoin(room, eq(room.id, reservation.roomId))
		.innerJoin(hotel, eq(hotel.id, room.hotelId))
		.where(eq(reservation.id, reservationId));

	if (!row) return undefined;

	const { nights, amount } = calculateNightsAndAmount(
		row.checkIn,
		row.checkOut,
		row.basePrice,
	);

	return {
		id: row.id,
		guestId: row.guestId,
		hotelName: row.hotelName,
		roomName: row.roomName,
		checkIn: row.checkIn,
		checkOut: row.checkOut,
		guestCount: row.guestCount,
		status: row.status,
		nights,
		amount,
	};
}
