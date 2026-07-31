"use server";

import { headers } from "next/headers";
import type { HotelListingInput, SubmitListingResult } from "./schema";
import {
	submitHotelListingCore,
	updateHotelListingCore,
} from "./submit-listing";

// Dedicated file-level "use server" module — the Client Component intake
// form (T-3B02) imports this, not submit-listing.ts directly. Next.js
// requires every exported function in a "use server" file to be a Server
// Action bound the same way; mixing in submit-listing.ts's plain helpers and
// Node-only imports (pg via lib/db/client) would pull them into the client
// bundle, which is exactly what broke without this split (see phase-3.md
// T-3B02 Changes for the resulting compile error).
export async function submitHotelListing(
	input: HotelListingInput,
): Promise<SubmitListingResult> {
	return submitHotelListingCore(await headers(), input);
}

export async function updateHotelListing(
	hotelId: string,
	input: HotelListingInput,
): Promise<SubmitListingResult> {
	return updateHotelListingCore(await headers(), hotelId, input);
}
