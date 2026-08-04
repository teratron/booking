"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { resolvePaymentAction } from "@/lib/reservation/checkout-actions";
import type { PaymentOutcome } from "@/lib/reservation/payment-provider";

export function SimulatePaymentButtons({
	reservationId,
	successLabel,
	failureLabel,
	pendingLabel,
	errorLabel,
}: {
	reservationId: string;
	successLabel: string;
	failureLabel: string;
	pendingLabel: string;
	errorLabel: string;
}) {
	const router = useRouter();
	const [successPending, setSuccessPending] = useState(false);
	const [failurePending, setFailurePending] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const anyPending = successPending || failurePending;

	async function handleResolve(outcome: PaymentOutcome) {
		setError(null);
		if (outcome === "success") {
			setSuccessPending(true);
		} else {
			setFailurePending(true);
		}

		const result = await resolvePaymentAction(reservationId, outcome);

		if (result.success) {
			// A plain client navigation does not by itself invalidate the Router
			// Cache — router.refresh() forces the reservations list to pick up
			// the status this call just resolved instead of a stale cached
			// render (same fix auth-nav.tsx applies after sign-out).
			router.push("/account/reservations");
			router.refresh();
			return;
		}

		setSuccessPending(false);
		setFailurePending(false);
		setError(errorLabel);
	}

	return (
		<div className="space-y-3">
			<div className="flex flex-wrap gap-3">
				<Button disabled={anyPending} onClick={() => handleResolve("success")}>
					{successPending ? pendingLabel : successLabel}
				</Button>
				<Button
					variant="outline"
					disabled={anyPending}
					onClick={() => handleResolve("failure")}
				>
					{failurePending ? pendingLabel : failureLabel}
				</Button>
			</div>
			{error ? (
				<p role="alert" className="text-sm text-destructive">
					{error}
				</p>
			) : null}
		</div>
	);
}
