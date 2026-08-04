/**
 * Shared nights/amount derivation for the payment flow — used by both
 * `initiatePaymentCore` (checkout.ts) and `getReservationForCheckout`
 * (checkout-query.ts) so the amount sent to the payment provider and the
 * amount shown to the guest never drift apart.
 *
 * checkIn/checkOut are "YYYY-MM-DD" date-only strings (the `date` column's
 * default string mode). `new Date("YYYY-MM-DD")` parses a date-only ISO
 * string as UTC midnight, so subtracting the two is an exact, DST-safe night
 * count with zero timezone risk — do NOT "fix" this into something that
 * reads local timezone offsets. (This is the opposite direction from
 * formatting a locally-picked Date back into a "YYYY-MM-DD" string, which
 * DOES carry timezone risk and is handled elsewhere via local getters.)
 *
 * `basePrice` is Drizzle's decimal-as-string representation of
 * `room.basePrice`. The nights * basePrice product is rounded to 2 decimal
 * places (whole cents) before being returned — plain floating-point
 * multiplication can otherwise produce a non-terminating binary fraction
 * (e.g. "19.99" * 3 = 59.96999999999999) instead of a clean currency value.
 */
export function calculateNightsAndAmount(
	checkIn: string,
	checkOut: string,
	basePrice: string,
): { nights: number; amount: number } {
	const nights =
		(new Date(checkOut).getTime() - new Date(checkIn).getTime()) / 86_400_000;
	const amount = Math.round(Number(basePrice) * nights * 100) / 100;

	return { nights, amount };
}
