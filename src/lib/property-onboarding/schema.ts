import { z } from "zod";

// Pure zod schemas/types only — no server-only imports (db, auth session).
// The intake form (Client Component, T-3B02) needs these directly; anything
// that touches the database belongs in submit-listing.ts instead, or a
// bundler will pull the whole server-only module graph into the client
// bundle the moment a Client Component imports this file for its types.

// Hotel media supports photo + video; room media is photo-only — this mirrors
// the mediaTypeEnum/roomMedia schema shape from Phase 1, not a new decision.
const hotelMediaInputSchema = z.object({
	url: z.string().url(),
	type: z.enum(["photo", "video"]),
});

const roomInputSchema = z.object({
	name: z.string().min(1),
	bedConfiguration: z.string().optional(),
	guestCapacity: z.coerce.number().int().min(1),
	basePrice: z.coerce.number().positive(),
	featureTags: z.array(z.string()).optional(),
	amenityIds: z.array(z.string().uuid()).optional(),
	mediaUrls: z.array(z.string().url()).optional(),
});

// Mirrors db/schema.ts's accommodationTypeEnum values. Duplicated as plain
// strings rather than imported — db/schema.ts has no server-only imports
// itself today, but re-importing from it here would make this client-safe
// file's safety depend on that staying true forever. Postgres's own enum
// constraint is the fail-safe if the two lists ever drift.
export const accommodationTypeValues = [
	"hotel",
	"hostel",
	"apartment",
	"guesthouse",
	"resort",
] as const;

export const hotelListingInputSchema = z.object({
	name: z.string().min(1),
	accommodationType: z.enum(accommodationTypeValues).default("hotel"),
	starCategory: z.coerce.number().int().min(1).max(5).optional(),
	address: z.string().min(1),
	latitude: z.coerce.number().min(-90).max(90),
	longitude: z.coerce.number().min(-180).max(180),
	phone: z.string().min(1),
	amenityIds: z.array(z.string().uuid()).optional(),
	media: z.array(hotelMediaInputSchema).optional(),
	// Repeatable room blocks within one submission — l1-property-onboarding.md
	// §5.1's "Room section (repeatable)" resolved as one hotel + N rooms per
	// submission, not one submission per room.
	rooms: z.array(roomInputSchema).min(1),
});

export type HotelListingInput = z.infer<typeof hotelListingInputSchema>;

export type SubmitListingResult =
	| { success: true; hotelId: string }
	| {
			success: false;
			// NOT_FOUND/FORBIDDEN/NOT_EDITABLE are only reachable from
			// updateHotelListing — kept in one union since both flows share the
			// same success shape and callers already branch on `success`.
			error:
				| "UNAUTHENTICATED"
				| "VALIDATION_ERROR"
				| "NOT_FOUND"
				| "FORBIDDEN"
				| "NOT_EDITABLE";
			issues?: z.ZodIssue[];
	  };
