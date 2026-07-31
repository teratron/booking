import { desc, eq, sql } from "drizzle-orm";
import { db } from "@/lib/db/client";
import { article } from "@/lib/db/schema";

export const ARTICLE_PAGE_SIZE = 12;

export type ArticleSummary = {
	id: string;
	title: string;
	coverImage: string;
	summary: string;
	publishedAt: Date;
};

export type ArticleDetail = ArticleSummary & {
	content: string;
	hotelId: string | null;
};

export type ArticlePage = {
	results: ArticleSummary[];
	total: number;
	page: number;
	pageSize: number;
};

const summaryColumns = {
	id: article.id,
	title: article.title,
	coverImage: article.coverImage,
	summary: article.summary,
	publishedAt: article.publishedAt,
};

/**
 * Blog listing, newest first. Articles skip the moderation checkpoint
 * entirely — l1-content-publishing.md §3 resolves them as admin-authored, so
 * there is no `status` column to filter on (unlike hotel/room/review).
 */
export async function getArticles(page = 1): Promise<ArticlePage> {
	const [{ total }] = await db
		.select({ total: sql<number>`count(*)::int` })
		.from(article);

	const results = await db
		.select(summaryColumns)
		.from(article)
		.orderBy(desc(article.publishedAt))
		.limit(ARTICLE_PAGE_SIZE)
		.offset((page - 1) * ARTICLE_PAGE_SIZE);

	return { results, total, page, pageSize: ARTICLE_PAGE_SIZE };
}

export async function getArticleById(
	id: string,
): Promise<ArticleDetail | undefined> {
	const [row] = await db
		.select()
		.from(article)
		.where(eq(article.id, id))
		.limit(1);
	return row;
}

/**
 * The hotel profile page's news section (T-5B02) — articles associated with
 * one hotel, most recent first. A hotel with no news gets an empty array,
 * not an error, per l1-hotel-profile.md §3's independent-section-degradation
 * invariant.
 */
export async function getHotelNews(
	hotelId: string,
	limit = 3,
): Promise<ArticleSummary[]> {
	return db
		.select(summaryColumns)
		.from(article)
		.where(eq(article.hotelId, hotelId))
		.orderBy(desc(article.publishedAt))
		.limit(limit);
}
