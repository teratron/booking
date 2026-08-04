import { headers } from "next/headers";
import Link from "next/link";
import { redirect } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { getCurrentUser } from "@/lib/auth/session";
import { formatDate } from "@/lib/format-date";
import { getGuestReservations } from "@/lib/reservation/reservation-query";

const STATUS_VARIANT = {
	pending: "secondary",
	paid: "default",
	payment_failed: "destructive",
	cancelled: "outline",
} as const;

export default async function AccountReservationsPage() {
	const currentUser = await getCurrentUser(await headers());
	if (!currentUser) {
		redirect("/sign-in");
	}

	const [t, reservations] = await Promise.all([
		getTranslations("AccountReservations"),
		getGuestReservations(currentUser.id),
	]);

	const statusLabel = {
		pending: t("statusPending"),
		paid: t("statusPaid"),
		payment_failed: t("statusPaymentFailed"),
		cancelled: t("statusCancelled"),
	};

	return (
		<div className="mx-auto max-w-3xl px-4 py-12">
			<h1 className="mb-6 text-2xl font-semibold">{t("title")}</h1>

			{reservations.length === 0 ? (
				<p className="text-muted-foreground">{t("emptyState")}</p>
			) : (
				<ul className="space-y-4">
					{reservations.map((reservation) => (
						<li key={reservation.id} className="rounded-lg border p-4">
							<div className="flex items-center justify-between gap-4">
								<div>
									<p className="font-medium">{reservation.hotelName}</p>
									<p className="text-sm text-muted-foreground">
										{reservation.roomName}
									</p>
								</div>
								<Badge variant={STATUS_VARIANT[reservation.status]}>
									{statusLabel[reservation.status]}
								</Badge>
							</div>
							<p className="mt-2 text-sm text-muted-foreground">
								{t("datesLabel", {
									checkIn: formatDate(new Date(reservation.checkIn)),
									checkOut: formatDate(new Date(reservation.checkOut)),
								})}
							</p>
							<p className="text-sm text-muted-foreground">
								{t("guestsLabel", { count: reservation.guestCount })}
							</p>
							{reservation.status === "pending" ? (
								<div className="mt-3">
									<Button
										size="sm"
										render={
											<Link
												href={`/account/reservations/${reservation.id}/checkout`}
											/>
										}
									>
										{t("payNowLabel")}
									</Button>
								</div>
							) : null}
						</li>
					))}
				</ul>
			)}
		</div>
	);
}
