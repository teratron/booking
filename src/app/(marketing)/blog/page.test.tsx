import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import type {
	ArticlePage as ArticlePageResult,
	ArticleSummary,
} from "@/lib/content/article-query";
import { getArticles } from "@/lib/content/article-query";
import messages from "../../../../messages/ru.json";
import BlogPage from "./page";

vi.mock("next-intl/server", () => ({
	getTranslations: async (namespace: string) => {
		const bundle = (messages as Record<string, Record<string, string>>)[
			namespace
		];
		return (key: string, values?: Record<string, string | number>) => {
			const text = bundle[key];
			if (!values) return text;
			return Object.entries(values).reduce(
				(result, [name, value]) =>
					result.replaceAll(`{${name}}`, String(value)),
				text,
			);
		};
	},
}));

// getArticles' own pagination/ordering correctness is proven against a real
// database in article-query.test.ts (T-5A01) — this page only needs to prove
// it parses the page param, forwards it, and renders cards/pagination from
// whatever it gets back.
vi.mock("@/lib/content/article-query", async (importOriginal) => {
	const actual =
		await importOriginal<typeof import("@/lib/content/article-query")>();
	return { ...actual, getArticles: vi.fn() };
});

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

function makeArticle(overrides: Partial<ArticleSummary> = {}): ArticleSummary {
	return {
		id: "article-1",
		title: "Fixture Article",
		coverImage: "https://example.test/cover.jpg",
		summary: "A fixture article summary",
		publishedAt: new Date("2026-01-15"),
		...overrides,
	};
}

function mockPage(overrides: Partial<ArticlePageResult>) {
	vi.mocked(getArticles).mockResolvedValue({
		results: [],
		total: 0,
		page: 1,
		pageSize: 12,
		...overrides,
	});
}

test("renders each article's title and summary, and calls getArticles with the requested page", async () => {
	mockPage({
		results: [
			makeArticle({ id: "a", title: "First Article" }),
			makeArticle({ id: "b", title: "Second Article" }),
		],
		total: 2,
		page: 2,
	});

	const element = await BlogPage({
		searchParams: Promise.resolve({ page: "2" }),
	});
	render(element);

	expect(getArticles).toHaveBeenCalledWith(2);
	expect(screen.getByText("First Article")).toBeDefined();
	expect(screen.getByText("Second Article")).toBeDefined();
});

test("shows the empty state when there are no articles", async () => {
	mockPage({ results: [], total: 0 });

	const element = await BlogPage({ searchParams: Promise.resolve({}) });
	render(element);

	expect(screen.getByText("Пока нет опубликованных статей.")).toBeDefined();
	expect(screen.queryByRole("navigation")).toBeNull();
});

test("hides pagination when everything fits on one page, shows it with correct hrefs otherwise", async () => {
	mockPage({ results: [makeArticle()], total: 25, page: 1 });

	const element = await BlogPage({ searchParams: Promise.resolve({}) });
	render(element);

	const next = screen.getByRole("link", { name: "Вперёд" });
	expect(next.getAttribute("href")).toBe("/blog?page=2");
	expect(screen.queryByRole("link", { name: "Назад" })).toBeNull();
});
