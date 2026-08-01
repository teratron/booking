import { z } from "zod";

// Pure zod schemas/types only — no server-only imports (db, auth session).
// The room-detail dialog (Client Component, T-6B01) needs these directly;
// anything that touches the database belongs in create-reservation.ts
// instead, mirroring lib/property-onboarding/schema.ts's own convention.

const dateKeySchema = z
	.string()
	.regex(/^\d{4}-\d{2}-\d{2}$/, "Expected a YYYY-MM-DD date");

export const reservationInputSchema = z
	.object({
		roomId: z.string().uuid(),
		checkIn: dateKeySchema,
		checkOut: dateKeySchema,
		guestCount: z.coerce.number().int().min(1),
	})
	.refine((data) => data.checkIn < data.checkOut, {
		message: "checkOut must be after checkIn",
		path: ["checkOut"],
	});

export type ReservationInput = z.infer<typeof reservationInputSchema>;

export type CreateReservationResult =
	| { success: true; reservationId: string }
	| {
			success: false;
			error:
				| "UNAUTHENTICATED"
				| "VALIDATION_ERROR"
				| "ROOM_NOT_FOUND"
				| "CAPACITY_EXCEEDED"
				| "UNAVAILABLE";
			issues?: z.ZodIssue[];
	  };
