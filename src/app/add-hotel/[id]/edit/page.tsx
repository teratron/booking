import { headers } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { amenity } from "@/lib/db/schema";
import { getHotelForEdit } from "@/lib/property-onboarding/queries";
import { HotelListingForm } from "../../hotel-listing-form";

export default async function EditHotelListingPage({
	params,
}: {
	params: Promise<{ id: string }>;
}) {
	const { id } = await params;
	const currentUser = await getCurrentUser(await headers());
	if (!currentUser) {
		redirect("/sign-in");
	}

	const result = await getHotelForEdit(id);
	// A non-owner or a listing not in "rejected" state both resolve to 404 —
	// not 403 — so the response doesn't confirm the resource exists to a
	// caller who has no business seeing it.
	if (
		!result ||
		result.hotel.ownerId !== currentUser.id ||
		result.hotel.status !== "rejected"
	) {
		notFound();
	}

	const amenities = await db.select().from(amenity);

	return (
		<div className="mx-auto max-w-3xl px-4 py-12">
			<HotelListingForm
				amenities={amenities}
				hotelId={result.hotel.id}
				defaultValues={result.formValues}
			/>
		</div>
	);
}
