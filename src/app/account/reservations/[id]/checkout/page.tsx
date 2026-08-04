import { headers } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { getCurrentUser } from "@/lib/auth/session";
import { formatDate } from "@/lib/format-date";
import { getReservationForCheckout } from "@/lib/reservation/checkout-query";
import { SimulatePaymentButtons } from "./simulate-payment-buttons";

export default async function ReservationCheckoutPage({
	params,
}: {
	params: Promise<{ id: string }>;
}) {
	const { id } = await params;
	const requestHeaders = await headers();

	const currentUser = await getCurrentUser(requestHeaders);
	if (!currentUser) {
		redirect("/sign-in");
	}

	const reservationDetail = await getReservationForCheckout(id);
	// A reservation that doesn't exist, or belongs to someone else, reads as
	// "not found" either way — never reveal to a guest that a given
	// reservation id belongs to another account.
	if (!reservationDetail || reservationDetail.guestId !== currentUser.id) {
		notFound();
	}
	if (reservationDetail.status !== "pending") {
		// Already paid/failed/cancelled — nothing to do here, most likely a
		// stale link or a double-click on "Pay now".
		redirect("/account/reservations");
	}

	// Initiating a payment attempt is a mutation (it calls the payment
	// provider and persists a reference) — it must not run as a side effect of
	// this GET-triggered Server Component render (a forged cross-site request,
	// aggressive Link prefetching, or a crawler could otherwise re-trigger it
	// with no user gesture required). It runs instead behind
	// `initiatePaymentAction`, invoked by SimulatePaymentButtons on the
	// guest's explicit click, right before resolving the outcome.
	const [t, accountT] = await Promise.all([
		getTranslations("Checkout"),
		getTranslations("AccountReservations"),
	]);

	return (
		<div className="mx-auto max-w-xl px-4 py-12">
			<h1 className="mb-6 text-2xl font-semibold">{t("title")}</h1>

			<div className="space-y-3 rounded-lg border p-4">
				<h2 className="text-lg font-medium">{t("summaryTitle")}</h2>
				<div>
					<p className="font-medium">{reservationDetail.hotelName}</p>
					<p className="text-sm text-muted-foreground">
						{reservationDetail.roomName}
					</p>
				</div>
				<p className="text-sm text-muted-foreground">
					{accountT("datesLabel", {
						checkIn: formatDate(new Date(reservationDetail.checkIn)),
						checkOut: formatDate(new Date(reservationDetail.checkOut)),
					})}
				</p>
				<p className="text-sm text-muted-foreground">
					{accountT("guestsLabel", { count: reservationDetail.guestCount })}
				</p>
				<p className="text-base font-semibold">
					{t("amountLabel", { amount: reservationDetail.amount.toFixed(2) })}
				</p>
			</div>

			<div className="mt-4 rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
				{t("noticeText")}
			</div>

			<div className="mt-6">
				<SimulatePaymentButtons
					reservationId={id}
					successLabel={t("successButtonLabel")}
					failureLabel={t("failureButtonLabel")}
					pendingLabel={t("pendingButtonLabel")}
					errorLabel={t("errorLabel")}
				/>
			</div>
		</div>
	);
}
