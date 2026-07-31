import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test } from "vitest";
import {
	RECENTLY_VIEWED_STORAGE_KEY,
	RecentlyViewedRail,
} from "./recently-viewed";

afterEach(() => {
	cleanup();
	window.localStorage.clear();
});

test("renders nothing when there is no other hotel to show", () => {
	render(
		<RecentlyViewedRail
			currentHotel={{ id: "hotel-1", name: "Current Hotel" }}
			title="Recently viewed"
		/>,
	);

	expect(screen.queryByRole("heading")).toBeNull();
});

test("writes the current hotel to localStorage on mount, excluding it from the list it renders", () => {
	render(
		<RecentlyViewedRail
			currentHotel={{ id: "hotel-1", name: "Current Hotel" }}
			title="Recently viewed"
		/>,
	);

	const stored = JSON.parse(
		window.localStorage.getItem(RECENTLY_VIEWED_STORAGE_KEY) ?? "[]",
	);
	expect(stored).toEqual([{ id: "hotel-1", name: "Current Hotel" }]);
	expect(screen.queryByText("Current Hotel")).toBeNull();
});

test("renders a previously-stored hotel and excludes the currently-viewed one, even if both were stored before", () => {
	window.localStorage.setItem(
		RECENTLY_VIEWED_STORAGE_KEY,
		JSON.stringify([
			{ id: "hotel-1", name: "Current Hotel" },
			{ id: "hotel-2", name: "Other Hotel" },
		]),
	);

	render(
		<RecentlyViewedRail
			currentHotel={{ id: "hotel-1", name: "Current Hotel" }}
			title="Recently viewed"
		/>,
	);

	expect(screen.queryByText("Current Hotel")).toBeNull();
	const link = screen.getByRole("link", { name: "Other Hotel" });
	expect(link.getAttribute("href")).toBe("/hotel/hotel-2");
});
