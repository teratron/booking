import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, expect, test, vi } from "vitest";
import { HomeHeroSearch } from "./home-hero-search";

const push = vi.fn();
vi.mock("next/navigation", () => ({
	useRouter: () => ({ push }),
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

const PROPS = {
	destinationPlaceholder: "Куда едем?",
	datesPlaceholder: "Даты",
	guestsLabel: "Гости",
	decreaseGuestsLabel: "Уменьшить",
	increaseGuestsLabel: "Увеличить",
	submitLabel: "Найти",
};

test("submitting with a destination and guest count navigates to /catalog with those query params", async () => {
	const user = userEvent.setup();
	render(<HomeHeroSearch {...PROPS} />);

	await user.type(
		screen.getByPlaceholderText(PROPS.destinationPlaceholder),
		"Kyiv",
	);
	await user.click(
		screen.getByRole("button", { name: PROPS.increaseGuestsLabel }),
	);
	await user.click(screen.getByRole("button", { name: PROPS.submitLabel }));

	expect(push).toHaveBeenCalledTimes(1);
	const url = push.mock.calls[0][0] as string;
	expect(url.startsWith("/catalog?")).toBe(true);
	const params = new URLSearchParams(url.split("?")[1]);
	expect(params.get("destination")).toBe("Kyiv");
	expect(params.get("guests")).toBe("3");
});

test("submitting with no destination still navigates, carrying only the guest count", async () => {
	const user = userEvent.setup();
	render(<HomeHeroSearch {...PROPS} />);

	await user.click(screen.getByRole("button", { name: PROPS.submitLabel }));

	expect(push).toHaveBeenCalledTimes(1);
	const url = push.mock.calls[0][0] as string;
	const params = new URLSearchParams(url.split("?")[1]);
	expect(params.has("destination")).toBe(false);
	expect(params.get("guests")).toBe("2");
});
