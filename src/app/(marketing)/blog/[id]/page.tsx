import Image from "next/image";
import { notFound } from "next/navigation";
import { getArticleById } from "@/lib/content/article-query";
import { formatDate } from "@/lib/format-date";

export default async function ArticlePage({
	params,
}: {
	params: Promise<{ id: string }>;
}) {
	const { id } = await params;
	const article = await getArticleById(id);
	if (!article) notFound();

	return (
		<main className="mx-auto max-w-3xl space-y-6 px-4 py-10">
			<div className="relative aspect-16/9 w-full overflow-hidden rounded-xl bg-muted">
				<Image
					src={article.coverImage}
					alt={article.title}
					fill
					sizes="100vw"
					className="object-cover"
				/>
			</div>
			<div className="space-y-2">
				<h1 className="text-3xl font-semibold">{article.title}</h1>
				<p className="text-sm text-muted-foreground">
					{formatDate(article.publishedAt)}
				</p>
			</div>
			<div className="whitespace-pre-wrap text-base leading-relaxed">
				{article.content}
			</div>
		</main>
	);
}
