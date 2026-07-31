import { getTranslations } from "next-intl/server";
import { SignInForm } from "@/components/sign-in-form";

export default async function SignInPage() {
	const t = await getTranslations("SignIn");

	return (
		<div className="px-4 py-12">
			<SignInForm
				title={t("title")}
				description={t("description")}
				emailLabel={t("emailLabel")}
				passwordLabel={t("passwordLabel")}
				submitLabel={t("submitLabel")}
				submitPendingLabel={t("submitPendingLabel")}
				errorGeneric={t("errorGeneric")}
				signUpPrompt={t("signUpPrompt")}
				signUpLink={t("signUpLink")}
			/>
		</div>
	);
}
