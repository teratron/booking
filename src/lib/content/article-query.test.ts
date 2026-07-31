import { eq } from "drizzle-orm";
import { afterAll, beforeAll, expect, test } from "vitest";
import { db } from "@/lib/db/client";
import { article, hotel, user } from "@/lib/db/schema";
import {
	ARTICLE_PAGE_SIZE,
	getArticleById,
	getArticles,
	getHotelNews,
} from "./article-query";

const testArticleIds: string[] = [];
const testHotelIds: string[] = [];
let authorId: string;

beforeAll(async () => {
	const [author] = await db
		.insert(user)
		.values({
			id: "test-t5a01-author",
			name: "T5A01 Author",
			email: "t5a01-author@test.local",
			role: "admin",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T5A01 Author" } })
		.returning();
	authorId = author.id;

	const [hotelA] = await db
		.insert(hotel)
		.values({
			ownerId: authorId,
			name: "T5A01 Hotel A",
			address: "1 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	const [hotelB] = await db
		.insert(hotel)
		.values({
			ownerId: authorId,
			name: "T5A01 Hotel B",
			address: "2 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	testHotelIds.push(hotelA.id, hotelB.id);

	const now = Date.now();
	const rows = await db
		.insert(article)
		.values([
			{
				authorId,
				hotelId: hotelA.id,
				title: "T5A01 Hotel A News",
				coverImage: "https://example.test/a.jpg",
				summary: "News about Hotel A",
				content: "Full content about Hotel A",
				publishedAt: new Date(now - 2000),
			},
			{
				authorId,
				hotelId: null,
				title: "T5A01 Global Post",
				coverImage: "https://example.test/global.jpg",
				summary: "A global blog post",
				content: "Full global content",
				publishedAt: new Date(now - 1000),
			},
			{
				authorId,
				hotelId: hotelA.id,
				title: "T5A01 Hotel A News 2",
				coverImage: "https://example.test/a2.jpg",
				summary: "More news about Hotel A",
				content: "More full content about Hotel A",
				publishedAt: new Date(now),
			},
		])
		.returning();
	testArticleIds.push(...rows.map((row) => row.id));
});

afterAll(async () => {
	for (const id of testArticleIds) {
		await db.delete(article).where(eq(article.id, id));
	}
	for (const id of testHotelIds) {
		await db.delete(hotel).where(eq(hotel.id, id));
	}
	await db.delete(user).where(eq(user.id, authorId));
});

test("getArticles orders newest-first and reports pagination metadata", async () => {
	const page = await getArticles(1);
	expect(page.pageSize).toBe(ARTICLE_PAGE_SIZE);
	expect(page.page).toBe(1);
	expect(page.total).toBeGreaterThanOrEqual(3);

	const titles = page.results.map((a) => a.title);
	const indexNewest = titles.indexOf("T5A01 Hotel A News 2");
	const indexGlobal = titles.indexOf("T5A01 Global Post");
	const indexOldest = titles.indexOf("T5A01 Hotel A News");
	expect(indexNewest).toBeGreaterThanOrEqual(0);
	expect(indexNewest).toBeLessThan(indexGlobal);
	expect(indexGlobal).toBeLessThan(indexOldest);
});

test("getArticleById returns the full article body, or undefined for a missing id", async () => {
	const found = await getArticleById(testArticleIds[0]);
	expect(found?.title).toBe("T5A01 Hotel A News");
	expect(found?.content).toBe("Full content about Hotel A");

	const missing = await getArticleById("00000000-0000-0000-0000-000000000000");
	expect(missing).toBeUndefined();
});

test("getHotelNews scopes to one hotel, excluding unrelated and unassociated articles", async () => {
	const [hotelAId, hotelBId] = testHotelIds;
	const hotelANews = await getHotelNews(hotelAId);
	expect(hotelANews.map((a) => a.title)).toEqual([
		"T5A01 Hotel A News 2",
		"T5A01 Hotel A News",
	]);

	const hotelBNews = await getHotelNews(hotelBId);
	expect(hotelBNews).toEqual([]);
});
