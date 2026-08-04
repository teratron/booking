/**
 * The payment provider seam (T-6C01). A real Fondy integration plugs in here
 * later as a drop-in `PaymentProvider` implementation — no call-site rewrite
 * across the codebase, because `getPaymentProvider()` below is the only place
 * that names a concrete implementation.
 *
 * Deliberately NOT part of this interface: resolving a payment outcome
 * (success/failure). A real payment gateway does not expose a method you call
 * to "resolve" a payment — it calls YOUR server back, asynchronously, via a
 * webhook. That resolution/webhook-shaped entry point belongs in
 * checkout.ts's `resolvePaymentCore`, which is built separately from this
 * file. This interface only covers the half of the flow that originates on
 * our side: creating a payment attempt for an amount/currency/reservation
 * reference and handing the guest a checkout URL to complete it at.
 */

import { createSimulatedPaymentProvider } from "./simulated-payment-provider";

/** A created payment attempt, ready to be completed at `checkoutUrl`. */
export type PaymentAttempt = {
	reference: string;
	checkoutUrl: string;
};

/** The two terminal states a payment attempt resolves to, out-of-band. */
export type PaymentOutcome = "success" | "failure";

export interface PaymentProvider {
	/**
	 * Starts a payment attempt for a reservation. Must not throw for
	 * ordinary rejection paths — a real gateway's checkout-session creation
	 * can itself fail (network, invalid amount, etc.), but that is a
	 * separate concern from the guest declining or failing to pay once the
	 * session exists, which is reported later via the webhook path, not
	 * this method's return value.
	 */
	createPayment(input: {
		reservationId: string;
		amount: number;
		currency: string;
	}): Promise<PaymentAttempt>;
}

/**
 * Factory, not a bare exported constant: today it always returns the
 * simulated implementation, but the real-Fondy branch (env-flag or
 * config-driven) is a one-line change inside this function body once live
 * merchant/sandbox credentials exist, not a redesign of this file's exports
 * or of any call site.
 */
export function getPaymentProvider(): PaymentProvider {
	return createSimulatedPaymentProvider();
}
