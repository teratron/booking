import { getTranslations } from "next-intl/server";
import { SignUpForm } from "@/components/sign-up-form";

export default async function SignUpPage() {
	const t = await getTranslations("SignUp");

	return (
		<div className="px-4 py-12">
			<SignUpForm
				title={t("title")}
				description={t("description")}
				nameLabel={t("nameLabel")}
				emailLabel={t("emailLabel")}
				passwordLabel={t("passwordLabel")}
				passwordHint={t("passwordHint")}
				submitLabel={t("submitLabel")}
				submitPendingLabel={t("submitPendingLabel")}
				errorGeneric={t("errorGeneric")}
				signInPrompt={t("signInPrompt")}
				signInLink={t("signInLink")}
			/>
		</div>
	);
}
