import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, test } from "vitest";
import messages from "../../../../messages/ru.json";
import { FilterSidebar } from "./filter-sidebar";

afterEach(() => {
	cleanup();
});

function t(key: string, values?: Record<string, string | number>): string {
	const bundle = (messages as Record<string, Record<string, string>>)
		.CatalogFilters;
	const text = bundle[key];
	if (!values) return text;
	return Object.entries(values).reduce(
		(result, [name, value]) => result.replaceAll(`{${name}}`, String(value)),
		text,
	);
}

const ACCOMMODATION_TYPE_OPTIONS = [
	{ value: "hotel", label: "Отель" },
	{ value: "hostel", label: "Хостел" },
];

const HOTEL_AMENITIES = [
	{ id: "wifi-id", name: "Wi-Fi" },
	{ id: "pool-id", name: "Pool" },
];

const ROOM_AMENITIES = [{ id: "ac-id", name: "Air Conditioning" }];

test("is a GET form to /catalog, so a real browser submission produces query params directly", () => {
	render(
		<FilterSidebar
			searchParams={{}}
			hotelAmenities={HOTEL_AMENITIES}
			roomAmenities={ROOM_AMENITIES}
			accommodationTypeOptions={ACCOMMODATION_TYPE_OPTIONS}
			t={t}
		/>,
	);

	const form = screen.getByRole("form", { name: "Фильтры" });
	expect(form.getAttribute("method")).toBe("GET");
	expect(form.getAttribute("action")).toBe("/catalog");
});

test("hydrates every control's checked/value state from the current searchParams", () => {
	render(
		<FilterSidebar
			searchParams={{
				destination: "Kyiv",
				accommodationType: "hostel",
				minStarRating: "4",
				minPrice: "50",
				maxPrice: "200",
				amenityIds: ["wifi-id"],
				roomAmenityIds: ["ac-id"],
				sort: "price",
			}}
			hotelAmenities={HOTEL_AMENITIES}
			roomAmenities={ROOM_AMENITIES}
			accommodationTypeOptions={ACCOMMODATION_TYPE_OPTIONS}
			t={t}
		/>,
	);

	expect(
		(screen.getByLabelText("Местоположение") as HTMLInputElement).value,
	).toBe("Kyiv");
	expect(
		(screen.getByRole("radio", { name: "Хостел" }) as HTMLInputElement).checked,
	).toBe(true);
	expect(
		(screen.getByRole("radio", { name: "Отель" }) as HTMLInputElement).checked,
	).toBe(false);
	expect(
		(screen.getByRole("radio", { name: "4+ звёзд" }) as HTMLInputElement)
			.checked,
	).toBe(true);
	expect(
		(screen.getByRole("checkbox", { name: "Wi-Fi" }) as HTMLInputElement)
			.checked,
	).toBe(true);
	expect(
		(screen.getByRole("checkbox", { name: "Pool" }) as HTMLInputElement)
			.checked,
	).toBe(false);
	expect(
		(
			screen.getByRole("checkbox", {
				name: "Air Conditioning",
			}) as HTMLInputElement
		).checked,
	).toBe(true);

	const form = screen.getByRole("form", { name: "Фильтры" });
	const hiddenSort = form.querySelector('input[name="sort"][type="hidden"]');
	expect(hiddenSort?.getAttribute("value")).toBe("price");
});

test("the Any radio is checked and no hidden sort input renders when nothing is set", () => {
	render(
		<FilterSidebar
			searchParams={{}}
			hotelAmenities={HOTEL_AMENITIES}
			roomAmenities={ROOM_AMENITIES}
			accommodationTypeOptions={ACCOMMODATION_TYPE_OPTIONS}
			t={t}
		/>,
	);

	const anyRadios = screen.getAllByRole("radio", { name: "Любой" });
	for (const radio of anyRadios) {
		expect((radio as HTMLInputElement).checked).toBe(true);
	}

	const form = screen.getByRole("form", { name: "Фильтры" });
	expect(form.querySelector('input[name="sort"]')).toBeNull();
});

test("clearing filters links back to a bare /catalog", () => {
	render(
		<FilterSidebar
			searchParams={{ destination: "Kyiv", sort: "price" }}
			hotelAmenities={HOTEL_AMENITIES}
			roomAmenities={ROOM_AMENITIES}
			accommodationTypeOptions={ACCOMMODATION_TYPE_OPTIONS}
			t={t}
		/>,
	);

	const clearLink = screen.getByRole("link", { name: "Сбросить фильтры" });
	expect(clearLink.getAttribute("href")).toBe("/catalog");
});
