/**
 * SIMULATED PAYMENT PROVIDER — NOT A REAL PAYMENT INTEGRATION.
 *
 * This is a stand-in `PaymentProvider` implementation. It makes no external
 * network call, talks to no real payment gateway, and must never be
 * presented to a user as processing a real payment. It exists solely because
 * no live Fondy merchant/sandbox credentials exist in this environment; it
 * lets the rest of the checkout flow (T-6C01) be built and tested end-to-end
 * against a deterministic stand-in.
 *
 * What a real Fondy `PaymentProvider` implementation would need to do
 * differently:
 * - `createPayment` would call Fondy's checkout-session creation API with
 *   the merchant credentials, amount, currency, and order/reservation
 *   reference, and return the real Fondy-hosted `checkoutUrl` it responds
 *   with — not an internal app route.
 * - The success/failure outcome would NOT be produced by this file at all.
 *   It would arrive later, asynchronously, as a Fondy webhook callback whose
 *   signature must be verified before trusting the payload — that entry
 *   point is `checkout.ts`'s `resolvePaymentCore`, built separately from
 *   this file.
 * - Idempotency, retries, and error mapping for Fondy's actual API error
 *   codes would need to be handled — none of that exists here because there
 *   is no real API call to fail.
 */

import type { PaymentProvider } from "./payment-provider";

export function createSimulatedPaymentProvider(): PaymentProvider {
	return {
		async createPayment(input) {
			// No real network call — resolves immediately with a fabricated
			// reference and an internal route standing in for a payment
			// gateway's hosted checkout page.
			return {
				reference: `sim_${crypto.randomUUID()}`,
				checkoutUrl: `/account/reservations/${input.reservationId}/checkout`,
			};
		},
	};
}
