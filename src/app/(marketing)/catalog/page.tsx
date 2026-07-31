import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { LeafletMapLoader } from "@/components/leaflet-map-loader";
import { ResultCard } from "@/components/result-card";
import { getAmenityFacets } from "@/lib/discovery/amenity-facets";
import {
	getCatalogMapPins,
	getCatalogResults,
	PAGE_SIZE,
} from "@/lib/discovery/catalog-query";
import {
	buildCatalogHref,
	type CatalogSearchParams,
	parseCatalogQueryInput,
} from "@/lib/discovery/catalog-url";
import { FilterSidebar } from "./filter-sidebar";

const SORT_OPTIONS = [
	{ value: "popularity", labelKey: "sortPopularity" },
	{ value: "price", labelKey: "sortPrice" },
] as const;

const ACCOMMODATION_TYPES = [
	{ value: "hotel", labelKey: "accommodationTypeHotel" },
	{ value: "hostel", labelKey: "accommodationTypeHostel" },
	{ value: "apartment", labelKey: "accommodationTypeApartment" },
	{ value: "guesthouse", labelKey: "accommodationTypeGuesthouse" },
	{ value: "resort", labelKey: "accommodationTypeResort" },
] as const;

export default async function CatalogPage({
	searchParams,
}: {
	searchParams: Promise<CatalogSearchParams>;
}) {
	const resolvedSearchParams = await searchParams;
	const query = parseCatalogQueryInput(resolvedSearchParams);
	const t = await getTranslations("Catalog");
	const filtersT = await getTranslations("CatalogFilters");
	const resultCardT = await getTranslations("ResultCard");
	const addHotelT = await getTranslations("AddHotelForm");
	const [page, amenityFacets, mapPins] = await Promise.all([
		getCatalogResults(query),
		getAmenityFacets(),
		getCatalogMapPins(query),
	]);

	const activeSort = query.sort ?? "popularity";
	const totalPages = Math.max(1, Math.ceil(page.total / PAGE_SIZE));
	const accommodationTypeOptions = ACCOMMODATION_TYPES.map((option) => ({
		value: option.value,
		label: addHotelT(option.labelKey),
	}));

	return (
		<main className="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-4 py-10 lg:grid-cols-[280px_1fr]">
			<FilterSidebar
				searchParams={resolvedSearchParams}
				hotelAmenities={amenityFacets.hotelAmenities}
				roomAmenities={amenityFacets.roomAmenities}
				accommodationTypeOptions={accommodationTypeOptions}
				t={filtersT}
			/>

			<div className="space-y-6">
				<div className="flex flex-wrap items-center justify-between gap-3">
					<h1 className="text-2xl font-semibold">{t("title")}</h1>
					<div className="flex items-center gap-2 text-sm">
						<span className="text-muted-foreground">{t("sortLabel")}</span>
						{SORT_OPTIONS.map((option) => (
							<Link
								key={option.value}
								href={buildCatalogHref(resolvedSearchParams, {
									sort: option.value,
								})}
								aria-current={activeSort === option.value ? "true" : undefined}
								className={
									activeSort === option.value
										? "rounded-full bg-primary px-3 py-1 text-primary-foreground"
										: "rounded-full bg-secondary px-3 py-1 text-secondary-foreground hover:bg-secondary/80"
								}
							>
								{t(option.labelKey)}
							</Link>
						))}
					</div>
				</div>

				<LeafletMapLoader pins={mapPins} />

				<p className="text-sm text-muted-foreground">
					{t("resultsCount", { count: page.total })}
				</p>

				{page.results.length === 0 ? (
					<p className="text-muted-foreground">{t("emptyState")}</p>
				) : (
					<div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
						{page.results.map((result) => (
							<ResultCard
								key={result.id}
								result={result}
								fromLabel={resultCardT("fromLabel")}
								noReviewsLabel={resultCardT("noReviewsLabel")}
							/>
						))}
					</div>
				)}

				{totalPages > 1 ? (
					<nav
						aria-label={t("paginationLabel")}
						className="flex items-center justify-center gap-3 pt-4"
					>
						<PaginationLink
							href={buildCatalogHref(resolvedSearchParams, {
								page: String(Math.max(1, page.page - 1)),
							})}
							disabled={page.page <= 1}
							label={t("previousPageLabel")}
						/>
						<span className="text-sm text-muted-foreground">
							{t("pageIndicator", { page: page.page, totalPages })}
						</span>
						<PaginationLink
							href={buildCatalogHref(resolvedSearchParams, {
								page: String(Math.min(totalPages, page.page + 1)),
							})}
							disabled={page.page >= totalPages}
							label={t("nextPageLabel")}
						/>
					</nav>
				) : null}
			</div>
		</main>
	);
}

function PaginationLink({
	href,
	disabled,
	label,
}: {
	href: string;
	disabled: boolean;
	label: string;
}) {
	if (disabled) {
		return (
			<span className="rounded-md border px-3 py-1.5 text-sm text-muted-foreground opacity-50">
				{label}
			</span>
		);
	}
	return (
		<Link
			href={href}
			className="rounded-md border px-3 py-1.5 text-sm hover:bg-secondary"
		>
			{label}
		</Link>
	);
}
