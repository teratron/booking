import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { LanguageSwitcher } from "@/components/language-switcher";

export async function Header() {
	const t = await getTranslations("Header");

	const navLinks = [
		{ href: "/catalog", label: t("navCatalog") },
		{ href: "/catalog?view=map", label: t("navMap") },
		{ href: "/blog", label: t("navBlog") },
	];

	return (
		<header>
			<div className="flex items-center justify-between px-4 py-3">
				<Link href="/" className="text-lg font-semibold">
					Booking
				</Link>
				<nav aria-label={t("navLabel")}>
					{/* Mobile: collapsed behind a native disclosure — no JS needed. */}
					<details className="md:hidden">
						<summary
							className="cursor-pointer list-none"
							aria-label={t("menuLabel")}
							data-testid="mobile-menu-trigger"
						>
							☰
						</summary>
						<ul
							className="mt-2 flex flex-col gap-2"
							data-testid="mobile-nav-list"
						>
							{navLinks.map((link) => (
								<li key={link.href}>
									<Link href={link.href}>{link.label}</Link>
								</li>
							))}
						</ul>
					</details>
					{/* Desktop: plain inline list, hamburger hidden entirely. */}
					<ul
						className="hidden items-center gap-4 md:flex"
						data-testid="desktop-nav-list"
					>
						{navLinks.map((link) => (
							<li key={link.href}>
								<Link href={link.href}>{link.label}</Link>
							</li>
						))}
					</ul>
				</nav>
				<LanguageSwitcher />
			</div>
		</header>
	);
}
