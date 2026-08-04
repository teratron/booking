"use server";

import { headers } from "next/headers";
import {
	type InitiatePaymentResult,
	initiatePaymentCore,
	type ResolvePaymentResult,
	resolvePaymentCore,
} from "./checkout";
import type { PaymentOutcome } from "./payment-provider";

// Dedicated file-level "use server" module, separate from the persistence
// core (checkout.ts) — which imports `db` and must never reach a client
// bundle — mirroring actions.ts's split for reservation creation.
export async function initiatePaymentAction(
	reservationId: string,
): Promise<InitiatePaymentResult> {
	return initiatePaymentCore(await headers(), reservationId);
}

export async function resolvePaymentAction(
	reservationId: string,
	outcome: PaymentOutcome,
): Promise<ResolvePaymentResult> {
	return resolvePaymentCore(await headers(), reservationId, outcome);
}
