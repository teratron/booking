import Link from "next/link";
import type { AmenityOption } from "@/lib/discovery/amenity-facets";
import type { CatalogSearchParams } from "@/lib/discovery/catalog-url";

const STAR_RATINGS = [5, 4, 3, 2, 1] as const;

type Translate = (
	key: string,
	values?: Record<string, string | number>,
) => string;

function firstValue(value: string | string[] | undefined): string {
	return (Array.isArray(value) ? value[0] : value) ?? "";
}

function selectedIds(value: string | string[] | undefined): Set<string> {
	if (value === undefined) return new Set();
	return new Set(Array.isArray(value) ? value : [value]);
}

/**
 * A native GET <form> rather than a client-side control set — every input's
 * `name` becomes a query param the moment the browser submits it, so the
 * whole facet set (single-select radios, multi-select checkboxes, free-text
 * price range) stays in sync without any client JS or manual
 * href-per-toggle logic. Omitting a `page` input naturally resets pagination
 * to 1 on every filter change, satisfying l1-hotel-discovery.md §3's "a
 * filter change must not leave the user stranded on a now-out-of-range
 * page" without extra code. The current `sort` rides along as a hidden
 * input so applying a filter doesn't silently revert an explicit sort
 * choice back to the default.
 */
export function FilterSidebar({
	searchParams,
	hotelAmenities,
	roomAmenities,
	accommodationTypeOptions,
	t,
}: {
	searchParams: CatalogSearchParams;
	hotelAmenities: AmenityOption[];
	roomAmenities: AmenityOption[];
	accommodationTypeOptions: Array<{ value: string; label: string }>;
	t: Translate;
}) {
	const destination = firstValue(searchParams.destination);
	const accommodationType = firstValue(searchParams.accommodationType);
	const minStarRating = firstValue(searchParams.minStarRating);
	const minPrice = firstValue(searchParams.minPrice);
	const maxPrice = firstValue(searchParams.maxPrice);
	const sort = firstValue(searchParams.sort);
	const selectedAmenityIds = selectedIds(searchParams.amenityIds);
	const selectedRoomAmenityIds = selectedIds(searchParams.roomAmenityIds);

	return (
		<form
			method="GET"
			action="/catalog"
			aria-label={t("filtersLabel")}
			className="space-y-6 rounded-xl border p-4"
		>
			{sort ? <input type="hidden" name="sort" value={sort} /> : null}

			<div className="space-y-1.5">
				<label htmlFor="filter-destination" className="text-sm font-medium">
					{t("locationLabel")}
				</label>
				<input
					id="filter-destination"
					type="text"
					name="destination"
					defaultValue={destination}
					className="w-full rounded-md border px-3 py-1.5 text-sm"
				/>
			</div>

			<fieldset className="space-y-1.5">
				<legend className="text-sm font-medium">
					{t("accommodationTypeLabel")}
				</legend>
				<label className="flex items-center gap-2 text-sm">
					<input
						type="radio"
						name="accommodationType"
						value=""
						defaultChecked={accommodationType === ""}
					/>
					{t("anyLabel")}
				</label>
				{accommodationTypeOptions.map((option) => (
					<label key={option.value} className="flex items-center gap-2 text-sm">
						<input
							type="radio"
							name="accommodationType"
							value={option.value}
							defaultChecked={accommodationType === option.value}
						/>
						{option.label}
					</label>
				))}
			</fieldset>

			<fieldset className="space-y-1.5">
				<legend className="text-sm font-medium">{t("starRatingLabel")}</legend>
				<label className="flex items-center gap-2 text-sm">
					<input
						type="radio"
						name="minStarRating"
						value=""
						defaultChecked={minStarRating === ""}
					/>
					{t("anyLabel")}
				</label>
				{STAR_RATINGS.map((stars) => (
					<label key={stars} className="flex items-center gap-2 text-sm">
						<input
							type="radio"
							name="minStarRating"
							value={String(stars)}
							defaultChecked={minStarRating === String(stars)}
						/>
						{t("starsAndUpLabel", { stars })}
					</label>
				))}
			</fieldset>

			<div className="space-y-1.5">
				<span className="text-sm font-medium">{t("priceRangeLabel")}</span>
				<div className="flex items-center gap-2">
					<input
						type="number"
						name="minPrice"
						min="0"
						defaultValue={minPrice}
						placeholder={t("minPricePlaceholder")}
						aria-label={t("minPricePlaceholder")}
						className="w-full rounded-md border px-3 py-1.5 text-sm"
					/>
					<span className="text-muted-foreground">—</span>
					<input
						type="number"
						name="maxPrice"
						min="0"
						defaultValue={maxPrice}
						placeholder={t("maxPricePlaceholder")}
						aria-label={t("maxPricePlaceholder")}
						className="w-full rounded-md border px-3 py-1.5 text-sm"
					/>
				</div>
			</div>

			<fieldset className="space-y-1.5">
				<legend className="text-sm font-medium">{t("amenitiesLabel")}</legend>
				{hotelAmenities.map((option) => (
					<label key={option.id} className="flex items-center gap-2 text-sm">
						<input
							type="checkbox"
							name="amenityIds"
							value={option.id}
							defaultChecked={selectedAmenityIds.has(option.id)}
						/>
						{option.name}
					</label>
				))}
			</fieldset>

			<fieldset className="space-y-1.5">
				<legend className="text-sm font-medium">
					{t("roomFacilitiesLabel")}
				</legend>
				{roomAmenities.map((option) => (
					<label key={option.id} className="flex items-center gap-2 text-sm">
						<input
							type="checkbox"
							name="roomAmenityIds"
							value={option.id}
							defaultChecked={selectedRoomAmenityIds.has(option.id)}
						/>
						{option.name}
					</label>
				))}
			</fieldset>

			<div className="flex items-center gap-3 pt-2">
				<button
					type="submit"
					className="rounded-md bg-primary px-4 py-1.5 text-sm text-primary-foreground"
				>
					{t("applyLabel")}
				</button>
				<Link
					href="/catalog"
					className="text-sm text-muted-foreground hover:underline"
				>
					{t("clearLabel")}
				</Link>
			</div>
		</form>
	);
}
