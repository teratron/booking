import { eq } from "drizzle-orm";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import {
	hotel,
	hotelAmenity,
	hotelMedia,
	room,
	roomAmenity,
	roomMedia,
	user,
} from "@/lib/db/schema";
import {
	type HotelListingInput,
	hotelListingInputSchema,
	type SubmitListingResult,
} from "./schema";

type Tx = Parameters<Parameters<typeof db.transaction>[0]>[0];

async function linkAmenities(
	tx: Tx,
	junction: typeof hotelAmenity | typeof roomAmenity,
	entityIdField: "hotelId" | "roomId",
	entityId: string,
	amenityIds: string[] | undefined,
) {
	if (!amenityIds || amenityIds.length === 0) return;
	await tx.insert(junction).values(
		amenityIds.map((amenityId) => ({
			[entityIdField]: entityId,
			amenityId,
		})),
	);
}

async function insertHotelMedia(
	tx: Tx,
	hotelId: string,
	media: HotelListingInput["media"],
) {
	if (!media || media.length === 0) return;
	await tx.insert(hotelMedia).values(
		media.map((item, index) => ({
			hotelId,
			url: item.url,
			type: item.type,
			sortOrder: index,
		})),
	);
}

async function insertRoomMedia(
	tx: Tx,
	roomId: string,
	urls: string[] | undefined,
) {
	if (!urls || urls.length === 0) return;
	await tx
		.insert(roomMedia)
		.values(urls.map((url, index) => ({ roomId, url, sortOrder: index })));
}

async function insertRoomWithRelations(
	tx: Tx,
	hotelId: string,
	roomInput: HotelListingInput["rooms"][number],
) {
	const [insertedRoom] = await tx
		.insert(room)
		.values({
			hotelId,
			name: roomInput.name,
			bedConfiguration: roomInput.bedConfiguration,
			guestCapacity: roomInput.guestCapacity,
			basePrice: roomInput.basePrice.toFixed(2),
			featureTags: roomInput.featureTags,
		})
		.returning();

	await linkAmenities(
		tx,
		roomAmenity,
		"roomId",
		insertedRoom.id,
		roomInput.amenityIds,
	);
	await insertRoomMedia(tx, insertedRoom.id, roomInput.mediaUrls);
}

/**
 * Framework-agnostic core, mirroring lib/auth/session.ts's own pattern: takes
 * an explicit Headers object so it is directly unit-testable outside a
 * Next.js request scope. The actual Server Action wrapper bound to the
 * intake form lives in actions.ts — Next.js requires a "use server" function
 * to live in a file that is either entirely server-only or dedicated to
 * actions; this file also exports plain server-side helpers and gets
 * imported by tests, so it cannot carry the directive itself.
 */
export async function submitHotelListingCore(
	requestHeaders: Headers,
	input: HotelListingInput,
): Promise<SubmitListingResult> {
	const currentUser = await getCurrentUser(requestHeaders);
	if (!currentUser) {
		return { success: false, error: "UNAUTHENTICATED" };
	}

	const parsed = hotelListingInputSchema.safeParse(input);
	if (!parsed.success) {
		return {
			success: false,
			error: "VALIDATION_ERROR",
			issues: parsed.error.issues,
		};
	}
	const data = parsed.data;

	const hotelId = await db.transaction(async (tx) => {
		// A guest becomes attributed as an owner by the act of submitting —
		// see tasks/phase-3.md Decisions for the full rationale. Admins keep
		// their role; roles are not a hierarchy (T-2A04's invariant).
		if (currentUser.role === "guest") {
			await tx
				.update(user)
				.set({ role: "owner" })
				.where(eq(user.id, currentUser.id));
		}

		const [insertedHotel] = await tx
			.insert(hotel)
			.values({
				ownerId: currentUser.id,
				name: data.name,
				accommodationType: data.accommodationType,
				starCategory: data.starCategory,
				address: data.address,
				latitude: data.latitude,
				longitude: data.longitude,
				phone: data.phone,
			})
			.returning();

		await linkAmenities(
			tx,
			hotelAmenity,
			"hotelId",
			insertedHotel.id,
			data.amenityIds,
		);
		await insertHotelMedia(tx, insertedHotel.id, data.media);

		for (const roomInput of data.rooms) {
			await insertRoomWithRelations(tx, insertedHotel.id, roomInput);
		}

		return insertedHotel.id;
	});

	return { success: true, hotelId };
}

/**
 * Edit-and-resubmit path for a rejected listing (l1-property-onboarding.md
 * §5.2: "Rejected + reason → Returned to owner for edits → pending again").
 * Reuses the same insert helpers as submitHotelListingCore — the child rows
 * (rooms, amenity links, media) are cleared and rebuilt from the edited
 * input rather than diffed, since a full form resubmission always carries
 * the complete desired state.
 */
export async function updateHotelListingCore(
	requestHeaders: Headers,
	hotelId: string,
	input: HotelListingInput,
): Promise<SubmitListingResult> {
	const currentUser = await getCurrentUser(requestHeaders);
	if (!currentUser) {
		return { success: false, error: "UNAUTHENTICATED" };
	}

	const [existing] = await db.select().from(hotel).where(eq(hotel.id, hotelId));
	if (!existing) {
		return { success: false, error: "NOT_FOUND" };
	}
	if (existing.ownerId !== currentUser.id) {
		return { success: false, error: "FORBIDDEN" };
	}
	if (existing.status !== "rejected") {
		return { success: false, error: "NOT_EDITABLE" };
	}

	const parsed = hotelListingInputSchema.safeParse(input);
	if (!parsed.success) {
		return {
			success: false,
			error: "VALIDATION_ERROR",
			issues: parsed.error.issues,
		};
	}
	const data = parsed.data;

	await db.transaction(async (tx) => {
		// Deleting rooms cascades to room_amenity/room_media (Phase 1 FK
		// onDelete: "cascade"); hotel-level links have no parent row to cascade
		// from, so they're cleared explicitly.
		await tx.delete(room).where(eq(room.hotelId, hotelId));
		await tx.delete(hotelAmenity).where(eq(hotelAmenity.hotelId, hotelId));
		await tx.delete(hotelMedia).where(eq(hotelMedia.hotelId, hotelId));

		await tx
			.update(hotel)
			.set({
				name: data.name,
				accommodationType: data.accommodationType,
				starCategory: data.starCategory,
				address: data.address,
				latitude: data.latitude,
				longitude: data.longitude,
				phone: data.phone,
				status: "pending",
				moderationReason: null,
				updatedAt: new Date(),
			})
			.where(eq(hotel.id, hotelId));

		await linkAmenities(tx, hotelAmenity, "hotelId", hotelId, data.amenityIds);
		await insertHotelMedia(tx, hotelId, data.media);

		for (const roomInput of data.rooms) {
			await insertRoomWithRelations(tx, hotelId, roomInput);
		}
	});

	return { success: true, hotelId };
}
