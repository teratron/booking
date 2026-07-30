import { getTranslations } from "next-intl/server";

export default async function PrivacyPolicyPage() {
	const t = await getTranslations("PrivacyPolicy");

	return (
		<main className="flex flex-col gap-4 px-4 py-8">
			<h1 className="text-2xl font-semibold">{t("title")}</h1>
			<p>{t("body")}</p>
		</main>
	);
}
