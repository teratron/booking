import { expect, test, vi } from "vitest";

vi.mock("next/headers", () => ({
	headers: vi.fn(async () => new Headers()),
}));
vi.mock("next/navigation", () => ({
	redirect: vi.fn(() => {
		throw new Error("NEXT_REDIRECT");
	}),
}));

// This specific empty-Headers → getCurrentUser → better-auth getSession path
// is, for reasons not yet root-caused, the single most timeout-prone test
// under full-suite concurrency (flaked at 5s and 15s global timeouts even
// with the dev server stopped) despite completing in ~200ms in isolation. A
// generous per-test timeout here rather than raising the global one for all
// 32+ files.
test("redirects an unauthenticated visitor to /sign-in", async () => {
	const { redirect } = await import("next/navigation");
	const { default: NewHotelListingPage } = await import("./page");

	await expect(NewHotelListingPage()).rejects.toThrow("NEXT_REDIRECT");
	expect(redirect).toHaveBeenCalledWith("/sign-in");
}, 30000);
