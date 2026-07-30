import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { Footer } from "@/components/footer";
import { Header } from "@/components/header";

// Root-level not-found.tsx is Next.js's guaranteed global catch-all for any
// unmatched URL — the (marketing) route group's own not-found scope would
// NOT cover paths outside it (e.g. a typo under a future /admin route). Since
// this file sits at the root, above (marketing)/layout.tsx, it does not
// inherit the marketing chrome and renders it directly instead.
export default async function NotFound() {
	const t = await getTranslations("NotFound");

	return (
		<>
			<Header />
			<main className="flex flex-col items-center gap-4 px-4 py-16 text-center">
				<h1 className="text-2xl font-semibold">{t("title")}</h1>
				<p className="text-muted-foreground">{t("description")}</p>
				<Link href="/" className="underline">
					{t("homeLink")}
				</Link>
			</main>
			<Footer />
		</>
	);
}
