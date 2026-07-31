import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { ARTICLE_PAGE_SIZE, getArticles } from "@/lib/content/article-query";
import { ArticleCard } from "./article-card";

export default async function BlogPage({
	searchParams,
}: {
	searchParams: Promise<{ page?: string }>;
}) {
	const { page: pageParam } = await searchParams;
	const page = Math.max(1, Number(pageParam) || 1);
	const t = await getTranslations("Blog");
	const articlePage = await getArticles(page);
	const totalPages = Math.max(
		1,
		Math.ceil(articlePage.total / ARTICLE_PAGE_SIZE),
	);

	return (
		<main className="mx-auto max-w-5xl space-y-6 px-4 py-10">
			<h1 className="text-3xl font-semibold">{t("title")}</h1>

			{articlePage.results.length === 0 ? (
				<p className="text-muted-foreground">{t("emptyState")}</p>
			) : (
				<div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
					{articlePage.results.map((article) => (
						<ArticleCard key={article.id} article={article} />
					))}
				</div>
			)}

			{totalPages > 1 ? (
				<nav
					aria-label={t("paginationLabel")}
					className="flex items-center justify-center gap-3 pt-4"
				>
					<PaginationLink
						href={`/blog?page=${Math.max(1, page - 1)}`}
						disabled={page <= 1}
						label={t("previousPageLabel")}
					/>
					<span className="text-sm text-muted-foreground">
						{t("pageIndicator", { page, totalPages })}
					</span>
					<PaginationLink
						href={`/blog?page=${Math.min(totalPages, page + 1)}`}
						disabled={page >= totalPages}
						label={t("nextPageLabel")}
					/>
				</nav>
			) : null}
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
