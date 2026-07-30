import { getTranslations } from "next-intl/server";

// Static label for now — only `ru` ships initially (l1-platform-foundation.md
// §3 Localization-ready). Becomes interactive once additional locales exist.
export async function LanguageSwitcher() {
	const t = await getTranslations("LanguageSwitcher");

	return (
		<span
			role="status"
			aria-label={t("ariaLabel")}
			className="text-sm font-medium"
		>
			{t("current")}
		</span>
	);
}
