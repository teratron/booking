import Image from "next/image";
import Link from "next/link";
import { Card, CardContent } from "@/components/ui/card";
import type { ArticleSummary } from "@/lib/content/article-query";
import { formatDate } from "@/lib/format-date";

/**
 * Shared between the blog listing/hotel-news use of a summary card and,
 * per l1-hotel-profile.md §6's own note, the hotel profile's news section
 * (T-5B02) — reuses this component rather than a bespoke one.
 */
export function ArticleCard({ article }: { article: ArticleSummary }) {
	const href = `/blog/${article.id}`;
	return (
		<Card className="overflow-hidden">
			<Link href={href} className="block">
				<div className="relative aspect-16/9 w-full bg-muted">
					<Image
						src={article.coverImage}
						alt={article.title}
						fill
						sizes="(min-width: 768px) 33vw, 100vw"
						className="object-cover"
					/>
				</div>
			</Link>
			<CardContent className="space-y-1">
				<Link href={href} className="font-medium hover:underline">
					{article.title}
				</Link>
				<p className="line-clamp-2 text-sm text-muted-foreground">
					{article.summary}
				</p>
				<p className="text-xs text-muted-foreground">
					{formatDate(article.publishedAt)}
				</p>
			</CardContent>
		</Card>
	);
}
