import { headers } from "next/headers";
import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { amenity } from "@/lib/db/schema";
import { HotelListingForm } from "../hotel-listing-form";

export default async function NewHotelListingPage() {
	const currentUser = await getCurrentUser(await headers());
	if (!currentUser) {
		redirect("/sign-in");
	}

	const amenities = await db.select().from(amenity);

	return (
		<div className="mx-auto max-w-3xl px-4 py-12">
			<HotelListingForm amenities={amenities} />
		</div>
	);
}
