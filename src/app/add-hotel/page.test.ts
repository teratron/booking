import { expect, test, vi } from "vitest";

vi.mock("next/headers", () => ({
	headers: vi.fn(async () => new Headers()),
}));
vi.mock("next/navigation", () => ({
	redirect: vi.fn(() => {
		throw new Error("NEXT_REDIRECT");
	}),
}));

// See the identical note in add-hotel/new/page.test.ts — same empty-Headers
// path, same occasional full-suite timeout flakiness.
test("redirects an unauthenticated visitor to /sign-in", async () => {
	const { redirect } = await import("next/navigation");
	const { default: AddHotelDashboardPage } = await import("./page");

	await expect(AddHotelDashboardPage()).rejects.toThrow("NEXT_REDIRECT");
	expect(redirect).toHaveBeenCalledWith("/sign-in");
}, 30000);
