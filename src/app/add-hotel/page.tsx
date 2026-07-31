import { headers } from "next/headers";
import Link from "next/link";
import { redirect } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { getCurrentUser } from "@/lib/auth/session";
import { getOwnerListings } from "@/lib/property-onboarding/queries";

const STATUS_VARIANT = {
	pending: "secondary",
	published: "default",
	rejected: "destructive",
} as const;

export default async function AddHotelDashboardPage() {
	const currentUser = await getCurrentUser(await headers());
	if (!currentUser) {
		redirect("/sign-in");
	}

	const [t, listings] = await Promise.all([
		getTranslations("AddHotelDashboard"),
		getOwnerListings(currentUser.id),
	]);

	const statusLabel = {
		pending: t("statusPending"),
		published: t("statusPublished"),
		rejected: t("statusRejected"),
	};

	return (
		<div className="mx-auto max-w-3xl px-4 py-12">
			<div className="mb-6 flex items-center justify-between">
				<h1 className="text-2xl font-semibold">{t("title")}</h1>
				<Button render={<Link href="/add-hotel/new" />}>{t("newLabel")}</Button>
			</div>

			{listings.length === 0 ? (
				<p className="text-muted-foreground">{t("emptyState")}</p>
			) : (
				<ul className="space-y-4">
					{listings.map((listing) => (
						<li key={listing.id} className="rounded-lg border p-4">
							<div className="flex items-center justify-between gap-4">
								<span className="font-medium">{listing.name}</span>
								<Badge variant={STATUS_VARIANT[listing.status]}>
									{statusLabel[listing.status]}
								</Badge>
							</div>
							{listing.status === "rejected" ? (
								<div className="mt-2 space-y-1">
									{listing.moderationReason ? (
										<p className="text-sm text-destructive">
											{listing.moderationReason}
										</p>
									) : null}
									<Link
										href={`/add-hotel/${listing.id}/edit`}
										className="text-sm text-primary underline underline-offset-4"
									>
										{t("editLabel")}
									</Link>
								</div>
							) : null}
						</li>
					))}
				</ul>
			)}
		</div>
	);
}
