import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import type { ArticleDetail } from "@/lib/content/article-query";
import { getArticleById } from "@/lib/content/article-query";
import ArticlePage from "./page";

vi.mock("@/lib/content/article-query", async (importOriginal) => {
	const actual =
		await importOriginal<typeof import("@/lib/content/article-query")>();
	return { ...actual, getArticleById: vi.fn() };
});

const notFoundMock = vi.fn(() => {
	throw new Error("NEXT_NOT_FOUND");
});
vi.mock("next/navigation", () => ({
	notFound: () => notFoundMock(),
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

function makeArticle(overrides: Partial<ArticleDetail> = {}): ArticleDetail {
	return {
		id: "article-1",
		title: "Fixture Article",
		coverImage: "https://example.test/cover.jpg",
		summary: "A fixture summary",
		content: "Full fixture body content.",
		hotelId: null,
		publishedAt: new Date("2026-01-15"),
		...overrides,
	};
}

test("calls notFound() for a missing article id", async () => {
	vi.mocked(getArticleById).mockResolvedValueOnce(undefined);

	await expect(
		ArticlePage({ params: Promise.resolve({ id: "missing" }) }),
	).rejects.toThrow();
	expect(notFoundMock).toHaveBeenCalled();
});

test("renders the full article body for a real id", async () => {
	vi.mocked(getArticleById).mockResolvedValueOnce(makeArticle());

	const element = await ArticlePage({
		params: Promise.resolve({ id: "article-1" }),
	});
	render(element);

	expect(
		screen.getByRole("heading", { level: 1, name: "Fixture Article" }),
	).toBeDefined();
	expect(screen.getByText("Full fixture body content.")).toBeDefined();
});
