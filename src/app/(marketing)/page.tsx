import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { HomeHeroSearch } from "@/components/home-hero-search";
import { ResultCard } from "@/components/result-card";
import { getCatalogResults } from "@/lib/discovery/catalog-query";

// One-click filter presets — l1-hotel-discovery.md §3's "curated category
// shortcuts" resolved against accommodationType (a stable enum) rather than
// amenity ids (DB-generated UUIDs unsafe to hardcode here) — see
// tasks/phase-4.md Decisions.
const CATEGORY_SHORTCUTS = [
	{ labelKey: "categoryHotels", accommodationType: "hotel" },
	{ labelKey: "categoryHostels", accommodationType: "hostel" },
	{ labelKey: "categoryApartments", accommodationType: "apartment" },
	{ labelKey: "categoryResorts", accommodationType: "resort" },
] as const;

export default async function Home() {
	const t = await getTranslations("Home");
	const resultCardT = await getTranslations("ResultCard");
	const page = await getCatalogResults({ sort: "popularity", page: 1 });

	return (
		<main className="mx-auto max-w-6xl space-y-10 px-4 py-10">
			<div className="space-y-4">
				<h1 className="text-3xl font-semibold">{t("heroTitle")}</h1>
				<HomeHeroSearch
					destinationPlaceholder={t("destinationPlaceholder")}
					datesPlaceholder={t("datesPlaceholder")}
					guestsLabel={t("guestsLabel")}
					decreaseGuestsLabel={t("decreaseGuestsLabel")}
					increaseGuestsLabel={t("increaseGuestsLabel")}
					submitLabel={t("submitLabel")}
				/>
			</div>

			<div className="space-y-3">
				<h2 className="text-lg font-medium">{t("categoriesTitle")}</h2>
				<div className="flex flex-wrap gap-2">
					{CATEGORY_SHORTCUTS.map((shortcut) => (
						<Link
							key={shortcut.accommodationType}
							href={`/catalog?accommodationType=${shortcut.accommodationType}`}
							className="rounded-full bg-secondary px-4 py-2 text-sm text-secondary-foreground hover:bg-secondary/80"
						>
							{t(shortcut.labelKey)}
						</Link>
					))}
				</div>
			</div>

			<div className="space-y-3">
				<h2 className="text-lg font-medium">{t("resultsTitle")}</h2>
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
			</div>
		</main>
	);
}
