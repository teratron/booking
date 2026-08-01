"use server";

import { headers } from "next/headers";
import { createReservationCore } from "./create-reservation";
import {
	getRoomDetail,
	isRoomAvailable,
	type RoomDetail,
} from "./reservation-query";
import type { CreateReservationResult, ReservationInput } from "./schema";

// Dedicated file-level "use server" module, separate from the persistence
// core (create-reservation.ts) and the query module (reservation-query.ts)
// — both import `db`, which must never reach the room-detail dialog's client
// bundle. Mirrors lib/property-onboarding/actions.ts's split.
export async function createReservationAction(
	input: ReservationInput,
): Promise<CreateReservationResult> {
	return createReservationCore(await headers(), input);
}

export async function checkRoomAvailabilityAction(
	roomId: string,
	checkIn: string,
	checkOut: string,
): Promise<boolean> {
	return isRoomAvailable(roomId, checkIn, checkOut);
}

export async function getRoomDetailAction(
	roomId: string,
): Promise<RoomDetail | undefined> {
	return getRoomDetail(roomId);
}
