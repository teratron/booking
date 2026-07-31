import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { NextIntlClientProvider } from "next-intl";
import { afterEach, expect, test, vi } from "vitest";
import messages from "../../../messages/ru.json";
import { HotelListingForm } from "./hotel-listing-form";

const push = vi.fn();
const refresh = vi.fn();
vi.mock("next/navigation", () => ({
	useRouter: () => ({ push, refresh }),
}));

const submitHotelListing = vi.fn();
const updateHotelListing = vi.fn();
vi.mock("@/lib/property-onboarding/actions", () => ({
	submitHotelListing: (...args: unknown[]) => submitHotelListing(...args),
	updateHotelListing: (...args: unknown[]) => updateHotelListing(...args),
}));

const upload = vi.fn();
vi.mock("@vercel/blob/client", () => ({
	upload: (...args: unknown[]) => upload(...args),
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

const t = (key: string) =>
	(messages as { AddHotelForm: Record<string, string> }).AddHotelForm[key];

function renderForm(
	props: Partial<React.ComponentProps<typeof HotelListingForm>> = {},
) {
	return render(
		<NextIntlClientProvider locale="ru" messages={messages}>
			<HotelListingForm amenities={[]} {...props} />
		</NextIntlClientProvider>,
	);
}

test("submitting with a second, added room calls submitHotelListing with both rooms", async () => {
	submitHotelListing.mockResolvedValue({ success: true, hotelId: "abc" });
	const user = userEvent.setup();
	renderForm();

	await user.type(screen.getByLabelText(t("nameLabel")), "Grand Hotel");
	await user.type(screen.getByLabelText(t("addressLabel")), "1 Main St");
	await user.type(screen.getByLabelText(t("latitudeLabel")), "50.45");
	await user.type(screen.getByLabelText(t("longitudeLabel")), "30.52");
	await user.type(screen.getByLabelText(t("phoneLabel")), "+380000000000");

	const nameInputs = screen.getAllByLabelText(t("roomNameLabel"));
	await user.type(nameInputs[0], "Standard");
	await user.clear(screen.getAllByLabelText(t("guestCapacityLabel"))[0]);
	await user.type(screen.getAllByLabelText(t("guestCapacityLabel"))[0], "2");
	await user.clear(screen.getAllByLabelText(t("basePriceLabel"))[0]);
	await user.type(screen.getAllByLabelText(t("basePriceLabel"))[0], "100");

	await user.click(screen.getByRole("button", { name: t("addRoomLabel") }));

	const nameInputsAfterAdd = screen.getAllByLabelText(t("roomNameLabel"));
	expect(nameInputsAfterAdd).toHaveLength(2);
	await user.type(nameInputsAfterAdd[1], "Deluxe");
	await user.clear(screen.getAllByLabelText(t("guestCapacityLabel"))[1]);
	await user.type(screen.getAllByLabelText(t("guestCapacityLabel"))[1], "3");
	await user.clear(screen.getAllByLabelText(t("basePriceLabel"))[1]);
	await user.type(screen.getAllByLabelText(t("basePriceLabel"))[1], "150");

	await user.click(screen.getByRole("button", { name: t("submitLabel") }));

	expect(submitHotelListing).toHaveBeenCalledTimes(1);
	const submitted = submitHotelListing.mock.calls[0][0];
	expect(submitted.name).toBe("Grand Hotel");
	expect(submitted.rooms).toHaveLength(2);
	expect(submitted.rooms[0]).toMatchObject({
		name: "Standard",
		guestCapacity: 2,
	});
	expect(submitted.rooms[1]).toMatchObject({
		name: "Deluxe",
		guestCapacity: 3,
	});
	expect(push).toHaveBeenCalledWith("/add-hotel");
	expect(refresh).toHaveBeenCalled();
});

test("a validation-error result from the server action shows the localized message", async () => {
	submitHotelListing.mockResolvedValue({
		success: false,
		error: "VALIDATION_ERROR",
	});
	const user = userEvent.setup();
	renderForm();

	await user.type(screen.getByLabelText(t("nameLabel")), "Grand Hotel");
	await user.type(screen.getByLabelText(t("addressLabel")), "1 Main St");
	await user.type(screen.getByLabelText(t("latitudeLabel")), "50.45");
	await user.type(screen.getByLabelText(t("longitudeLabel")), "30.52");
	await user.type(screen.getByLabelText(t("phoneLabel")), "+380000000000");
	await user.type(screen.getAllByLabelText(t("roomNameLabel"))[0], "Standard");
	await user.clear(screen.getAllByLabelText(t("guestCapacityLabel"))[0]);
	await user.type(screen.getAllByLabelText(t("guestCapacityLabel"))[0], "2");
	await user.clear(screen.getAllByLabelText(t("basePriceLabel"))[0]);
	await user.type(screen.getAllByLabelText(t("basePriceLabel"))[0], "100");

	await user.click(screen.getByRole("button", { name: t("submitLabel") }));

	expect(await screen.findByRole("alert")).toBeDefined();
	expect(screen.getByText(t("errorValidation"))).toBeDefined();
	expect(push).not.toHaveBeenCalled();
});

test("uploading a hotel photo calls the blob upload route and shows a removable preview", async () => {
	upload.mockResolvedValue({
		url: "https://example.blob.vercel-storage.com/photo.jpg",
		contentType: "image/jpeg",
	});
	const user = userEvent.setup();
	renderForm();

	const file = new File(["fake-bytes"], "photo.jpg", { type: "image/jpeg" });
	const fileInput = screen.getByLabelText(t("hotelMediaLabel"));
	await user.upload(fileInput, file);

	expect(upload).toHaveBeenCalledWith(
		"photo.jpg",
		file,
		expect.objectContaining({
			access: "public",
			handleUploadUrl: "/api/upload",
		}),
	);
	expect(
		await screen.findByText(
			/https:\/\/example\.blob\.vercel-storage\.com\/photo\.jpg/,
		),
	).toBeDefined();

	await user.click(screen.getByRole("button", { name: t("removeMediaLabel") }));
	expect(
		screen.queryByText(
			/https:\/\/example\.blob\.vercel-storage\.com\/photo\.jpg/,
		),
	).toBeNull();
});

test("an uploaded room photo's URL is included in the submitted payload", async () => {
	upload.mockResolvedValue({
		url: "https://example.blob.vercel-storage.com/room.jpg",
		contentType: "image/jpeg",
	});
	submitHotelListing.mockResolvedValue({ success: true, hotelId: "abc" });
	const user = userEvent.setup();
	renderForm();

	await user.type(screen.getByLabelText(t("nameLabel")), "Grand Hotel");
	await user.type(screen.getByLabelText(t("addressLabel")), "1 Main St");
	await user.type(screen.getByLabelText(t("latitudeLabel")), "50.45");
	await user.type(screen.getByLabelText(t("longitudeLabel")), "30.52");
	await user.type(screen.getByLabelText(t("phoneLabel")), "+380000000000");
	await user.type(screen.getAllByLabelText(t("roomNameLabel"))[0], "Standard");
	await user.clear(screen.getAllByLabelText(t("guestCapacityLabel"))[0]);
	await user.type(screen.getAllByLabelText(t("guestCapacityLabel"))[0], "2");
	await user.clear(screen.getAllByLabelText(t("basePriceLabel"))[0]);
	await user.type(screen.getAllByLabelText(t("basePriceLabel"))[0], "100");

	const file = new File(["fake-bytes"], "room.jpg", { type: "image/jpeg" });
	await user.upload(screen.getByLabelText(t("roomMediaLabel")), file);
	await screen.findByText(
		/https:\/\/example\.blob\.vercel-storage\.com\/room\.jpg/,
	);

	await user.click(screen.getByRole("button", { name: t("submitLabel") }));

	expect(submitHotelListing).toHaveBeenCalledTimes(1);
	const submitted = submitHotelListing.mock.calls[0][0];
	expect(submitted.rooms[0].mediaUrls).toEqual([
		"https://example.blob.vercel-storage.com/room.jpg",
	]);
});

test("edit mode (hotelId provided) is pre-filled and submits via updateHotelListing, not submitHotelListing", async () => {
	updateHotelListing.mockResolvedValue({
		success: true,
		hotelId: "existing-id",
	});
	const user = userEvent.setup();
	renderForm({
		hotelId: "existing-id",
		defaultValues: {
			name: "Existing Hotel",
			accommodationType: "hotel",
			address: "Old Address",
			latitude: 10,
			longitude: 20,
			phone: "+10000000000",
			amenityIds: [],
			media: [],
			rooms: [
				{
					name: "Existing Room",
					guestCapacity: 2,
					basePrice: 80,
					amenityIds: [],
					mediaUrls: [],
				},
			],
		},
	});

	expect(
		(screen.getByLabelText(t("nameLabel")) as HTMLInputElement).value,
	).toBe("Existing Hotel");
	expect(
		screen.getByRole("button", { name: t("resubmitLabel") }),
	).toBeDefined();

	await user.click(screen.getByRole("button", { name: t("resubmitLabel") }));

	expect(updateHotelListing).toHaveBeenCalledTimes(1);
	expect(updateHotelListing.mock.calls[0][0]).toBe("existing-id");
	expect(submitHotelListing).not.toHaveBeenCalled();
	expect(push).toHaveBeenCalledWith("/add-hotel");
});
