import { cleanup, render, screen } from "@testing-library/react";
import { eq } from "drizzle-orm";
import { afterAll, afterEach, beforeAll, expect, test, vi } from "vitest";
import { db } from "@/lib/db/client";
import {
	amenity,
	article,
	hotel,
	hotelAmenity,
	hotelMedia,
	review,
	room,
	roomMedia,
	user,
} from "@/lib/db/schema";
import messages from "../../../../../messages/ru.json";
import HotelProfilePage from "./page";

// Phase 5 exit gate (T-5T01) — mirrors T-2T01/T-3T01/T-4T01's role. Unlike
// page.test.tsx (T-5B01/T-5B02, mocked getHotelProfile/getHotelNews), this
// file exercises the real query modules against a real database, proving
// the full route → query → render chain — including that the "every section
// degrades independently" invariant (l1-hotel-profile.md §3) holds at the
// assembled-page level, not just at T-5A02's own data-layer proof, and that
// the moderation checkpoint blocks a pending/rejected hotel at the route
// itself. Every query here is scoped to its own hotel id, so — unlike an
// unfiltered/loosely-scoped query — this file carries none of the
// cross-file Postgres fixture-visibility risk STATE.md documents.
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

vi.mock("@/components/leaflet-map-loader", () => ({
	LeafletMapLoader: () => <div data-testid="leaflet-map-loader" />,
}));

const notFoundMock = vi.fn(() => {
	throw new Error("NEXT_NOT_FOUND");
});
vi.mock("next/navigation", () => ({
	notFound: () => notFoundMock(),
}));

const testHotelIds: string[] = [];
const testArticleIds: string[] = [];
let ownerId: string;
let guestId: string;
let testAmenityId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-t5t01-owner",
			name: "T5T01 Owner",
			email: "t5t01-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T5T01 Owner" } })
		.returning();
	ownerId = owner.id;

	const [guest] = await db
		.insert(user)
		.values({
			id: "test-t5t01-guest",
			name: "T5T01 Guest",
			email: "t5t01-guest@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T5T01 Guest" } })
		.returning();
	guestId = guest.id;

	const [amenityRow] = await db
		.select({ id: amenity.id })
		.from(amenity)
		.limit(1);
	testAmenityId = amenityRow.id;
});

afterAll(async () => {
	for (const id of testArticleIds) {
		await db.delete(article).where(eq(article.id, id));
	}
	for (const id of testHotelIds) {
		await db.delete(hotel).where(eq(hotel.id, id));
	}
	await db.delete(user).where(eq(user.id, ownerId));
	await db.delete(user).where(eq(user.id, guestId));
});

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

test("a fully-populated published hotel renders every section", async () => {
	const [fullHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5T01 Full Hotel",
			address: "1 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			starCategory: 5,
			status: "published",
		})
		.returning();
	testHotelIds.push(fullHotel.id);

	await db.insert(hotelMedia).values({
		hotelId: fullHotel.id,
		url: "https://example.test/gallery.jpg",
		type: "photo",
		sortOrder: 0,
	});
	await db
		.insert(hotelAmenity)
		.values({ hotelId: fullHotel.id, amenityId: testAmenityId });

	const [publishedRoom] = await db
		.insert(room)
		.values({
			hotelId: fullHotel.id,
			name: "Suite",
			guestCapacity: 2,
			basePrice: "150.00",
			status: "published",
		})
		.returning();
	await db.insert(roomMedia).values({
		roomId: publishedRoom.id,
		url: "https://example.test/room.jpg",
		sortOrder: 0,
	});

	await db.insert(review).values({
		hotelId: fullHotel.id,
		guestId,
		rating: 5,
		comment: "Excellent stay",
		status: "published",
	});

	const [newsArticle] = await db
		.insert(article)
		.values({
			authorId: ownerId,
			hotelId: fullHotel.id,
			title: "T5T01 Hotel News",
			coverImage: "https://example.test/news.jpg",
			summary: "News summary",
			content: "News content",
		})
		.returning();
	testArticleIds.push(newsArticle.id);

	const element = await HotelProfilePage({
		params: Promise.resolve({ id: fullHotel.id }),
	});
	render(element);

	expect(
		screen.getByRole("heading", { level: 1, name: "T5T01 Full Hotel" }),
	).toBeDefined();
	expect(screen.getByText("Номера")).toBeDefined();
	expect(screen.getByText("Услуги отеля")).toBeDefined();
	expect(screen.getByText("Новости отеля")).toBeDefined();
	expect(screen.getByText("Отзывы гостей")).toBeDefined();
	expect(screen.getByText("Suite")).toBeDefined();
	expect(screen.getByText("Excellent stay")).toBeDefined();
	expect(screen.getByTestId("leaflet-map-loader")).toBeDefined();
});

test("a minimal published hotel with zero media/amenities/rooms/reviews/news still renders a usable page, with optional sections absent", async () => {
	const [minimalHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5T01 Minimal Hotel",
			address: "2 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	testHotelIds.push(minimalHotel.id);

	const element = await HotelProfilePage({
		params: Promise.resolve({ id: minimalHotel.id }),
	});
	render(element);

	expect(
		screen.getByRole("heading", { level: 1, name: "T5T01 Minimal Hotel" }),
	).toBeDefined();
	expect(screen.getByTestId("leaflet-map-loader")).toBeDefined();
	expect(screen.queryByText("Номера")).toBeNull();
	expect(screen.queryByText("Услуги отеля")).toBeNull();
	expect(screen.queryByText("Новости отеля")).toBeNull();
	expect(screen.queryByText("Отзывы гостей")).toBeNull();
});

test("a pending hotel resolves to notFound() at the route level, not just the query layer", async () => {
	const [pendingHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5T01 Pending Hotel",
			address: "3 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "pending",
		})
		.returning();
	testHotelIds.push(pendingHotel.id);

	await expect(
		HotelProfilePage({ params: Promise.resolve({ id: pendingHotel.id }) }),
	).rejects.toThrow();
	expect(notFoundMock).toHaveBeenCalled();
});

test("a rejected hotel resolves to notFound() at the route level too", async () => {
	const [rejectedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T5T01 Rejected Hotel",
			address: "4 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "rejected",
		})
		.returning();
	testHotelIds.push(rejectedHotel.id);

	await expect(
		HotelProfilePage({ params: Promise.resolve({ id: rejectedHotel.id }) }),
	).rejects.toThrow();
	expect(notFoundMock).toHaveBeenCalled();
});
