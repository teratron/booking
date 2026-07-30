import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { FeedbackPopup } from "@/components/feedback-popup";

// Contact details and social handles are placeholders — no real values have
// been provided yet; replace before launch.
const CONTACT = {
	phone: "+00 000 000 0000",
	email: "info@example.com",
};

const SOCIAL_LINKS = [
	{ href: "#", label: "Instagram" },
	{ href: "#", label: "Telegram" },
	{ href: "#", label: "Facebook" },
];

export async function Footer() {
	const t = await getTranslations("Footer");
	const feedback = await getTranslations("FeedbackPopup");

	return (
		<footer>
			<div
				className="flex flex-col gap-4 px-4 py-6 md:flex-row md:items-start md:justify-between"
				data-testid="footer-sections"
			>
				<Link href="/" className="text-lg font-semibold">
					Booking
				</Link>
				<nav aria-label={t("navLabel")}>
					<ul className="flex flex-wrap gap-4">
						<li>
							<Link href="/about">{t("about")}</Link>
						</li>
						<li>
							<Link href="/privacy-policy">{t("privacyPolicy")}</Link>
						</li>
						<li>
							<Link href="/add-hotel">{t("addHotel")}</Link>
						</li>
					</ul>
				</nav>
				<div className="flex flex-wrap gap-4 text-sm">
					<a href={`tel:${CONTACT.phone}`}>{CONTACT.phone}</a>
					<a href={`mailto:${CONTACT.email}`}>{CONTACT.email}</a>
				</div>
				<ul className="flex gap-4">
					{SOCIAL_LINKS.map((social) => (
						<li key={social.label}>
							<a href={social.href}>{social.label}</a>
						</li>
					))}
				</ul>
				<FeedbackPopup
					triggerLabel={feedback("triggerLabel")}
					title={feedback("title")}
					description={feedback("description")}
					nameLabel={feedback("nameLabel")}
					messageLabel={feedback("messageLabel")}
					submitLabel={feedback("submitLabel")}
					cancelLabel={feedback("cancelLabel")}
				/>
			</div>
		</footer>
	);
}
