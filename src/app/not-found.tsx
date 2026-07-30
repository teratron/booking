import Link from "next/link";
import { getTranslations } from "next-intl/server";

export default async function NotFound() {
	const t = await getTranslations("NotFound");

	return (
		<main className="flex flex-col items-center gap-4 px-4 py-16 text-center">
			<h1 className="text-2xl font-semibold">{t("title")}</h1>
			<p className="text-muted-foreground">{t("description")}</p>
			<Link href="/" className="underline">
				{t("homeLink")}
			</Link>
		</main>
	);
}
