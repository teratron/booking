import { readFileSync } from "node:fs";
import { expect, test } from "vitest";

// Header/Footer are async Server Components using next-intl/server, which
// refuses to run under Vitest's jsdom environment (see header.test.tsx) — the
// actual breakpoint behavior is verified against a live dev server instead
// (see phase-1 task record). This asserts the responsive utility classes that
// drive that behavior are still present in source, as a regression guard.

test("Header: mobile nav is hidden at md and up, desktop nav is hidden below md", () => {
	const source = readFileSync("src/components/header.tsx", "utf-8");
	expect(source).toContain('className="md:hidden"');
	expect(source).toMatch(/className="hidden items-center gap-4 md:flex"/);
});

test("Footer: sections stack vertically below md, row layout at md and up", () => {
	const source = readFileSync("src/components/footer.tsx", "utf-8");
	expect(source).toContain("flex-col gap-4 px-4 py-6 md:flex-row");
});
