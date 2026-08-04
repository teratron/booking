import { eq } from "drizzle-orm";
import { afterAll, afterEach, beforeAll, expect, test } from "vitest";
import { getCurrentUser } from "@/lib/auth/session";
import { db } from "@/lib/db/client";
import { hotel, reservation, room, user } from "@/lib/db/schema";
import {
	deleteTestUsers,
	signUpAndGetCookieHeaders,
} from "@/lib/test-helpers/auth";
import { initiatePaymentCore, resolvePaymentCore } from "./checkout";
import { createReservationCore } from "./create-reservation";

const testEmails: string[] = [];
let ownerId: string;
let hotelId: string;
let roomId: string;

beforeAll(async () => {
	const [owner] = await db
		.insert(user)
		.values({
			id: "test-t6c01-owner",
			name: "T6C01 Owner",
			email: "t6c01-owner@test.local",
		})
		.onConflictDoUpdate({ target: user.id, set: { name: "T6C01 Owner" } })
		.returning();
	ownerId = owner.id;

	const [publishedHotel] = await db
		.insert(hotel)
		.values({
			ownerId,
			name: "T6C01 Hotel",
			address: "1 Fixture Ave",
			latitude: 1,
			longitude: 1,
			phone: "+10000000000",
			status: "published",
		})
		.returning();
	hotelId = publishedHotel.id;

	const [publishedRoom] = await db
		.insert(room)
		.values({
			hotelId,
			name: "T6C01 Room",
			guestCapacity: 2,
			basePrice: "100.00",
			status: "published",
		})
		.returning();
	roomId = publishedRoom.id;
});

afterEach(async () => {
	// Removes any reservations the just-finished test created (by guestId),
	// including ones inserted directly (bypassing createReservationCore) to
	// simulate an already-`paid` competing reservation — reservation has no
	// onDelete cascade from room, so order matters here.
	await deleteTestUsers(testEmails.splice(0));
});

afterAll(async () => {
	await db.delete(hotel).where(eq(hotel.id, hotelId));
	await db.delete(user).where(eq(user.id, ownerId));
});

// --- initiatePaymentCore ----------------------------------------------------

test("initiatePaymentCore: rejects an unauthenticated caller", async () => {
	const result = await initiatePaymentCore(
		new Headers(),
		crypto.randomUUID(),
	);
	expect(result).toEqual({ success: false, error: "UNAUTHENTICATED" });
});

test("initiatePaymentCore: rejects a reservation that does not exist", async () => {
	const email = "test-checkout-initiate-notfound@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await initiatePaymentCore(
		requestHeaders,
		crypto.randomUUID(),
	);
	expect(result).toEqual({ success: false, error: "NOT_FOUND" });
});

test("initiatePaymentCore: rejects a non-owner caller", async () => {
	const ownerEmail = "test-checkout-initiate-owner@example.com";
	const strangerEmail = "test-checkout-initiate-stranger@example.com";
	testEmails.push(ownerEmail, strangerEmail);

	const ownerHeaders = await signUpAndGetCookieHeaders(ownerEmail);
	const created = await createReservationCore(ownerHeaders, {
		roomId,
		checkIn: "2026-09-15",
		checkOut: "2026-09-17",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	const strangerHeaders = await signUpAndGetCookieHeaders(strangerEmail);
	const result = await initiatePaymentCore(
		strangerHeaders,
		created.reservationId,
	);
	expect(result).toEqual({ success: false, error: "FORBIDDEN" });
});

test("initiatePaymentCore: rejects a reservation that isn't pending", async () => {
	const email = "test-checkout-initiate-notpending@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const created = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-09-10",
		checkOut: "2026-09-12",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	await db
		.update(reservation)
		.set({ status: "paid" })
		.where(eq(reservation.id, created.reservationId));

	const result = await initiatePaymentCore(
		requestHeaders,
		created.reservationId,
	);
	expect(result).toEqual({ success: false, error: "NOT_PENDING" });
});

test("initiatePaymentCore: persists a payment reference and returns a checkout url for a pending reservation", async () => {
	const email = "test-checkout-initiate-success@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const created = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-09-01",
		checkOut: "2026-09-05",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	const result = await initiatePaymentCore(
		requestHeaders,
		created.reservationId,
	);
	expect(result).toEqual({
		success: true,
		checkoutUrl: `/account/reservations/${created.reservationId}/checkout`,
	});

	const [row] = await db
		.select()
		.from(reservation)
		.where(eq(reservation.id, created.reservationId));
	expect(row.status).toBe("pending");
	expect(row.paymentReference).toMatch(/^sim_/);
});

// --- resolvePaymentCore ------------------------------------------------------

test("resolvePaymentCore: rejects an unauthenticated caller", async () => {
	const result = await resolvePaymentCore(
		new Headers(),
		crypto.randomUUID(),
		"success",
	);
	expect(result).toEqual({ success: false, error: "UNAUTHENTICATED" });
});

test("resolvePaymentCore: rejects a reservation that does not exist", async () => {
	const email = "test-checkout-resolve-notfound@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const result = await resolvePaymentCore(
		requestHeaders,
		crypto.randomUUID(),
		"success",
	);
	expect(result).toEqual({ success: false, error: "NOT_FOUND" });
});

test("resolvePaymentCore: rejects a non-owner caller", async () => {
	const ownerEmail = "test-checkout-resolve-owner@example.com";
	const strangerEmail = "test-checkout-resolve-stranger@example.com";
	testEmails.push(ownerEmail, strangerEmail);

	const ownerHeaders = await signUpAndGetCookieHeaders(ownerEmail);
	const created = await createReservationCore(ownerHeaders, {
		roomId,
		checkIn: "2026-09-25",
		checkOut: "2026-09-27",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	const strangerHeaders = await signUpAndGetCookieHeaders(strangerEmail);
	const result = await resolvePaymentCore(
		strangerHeaders,
		created.reservationId,
		"success",
	);
	expect(result).toEqual({ success: false, error: "FORBIDDEN" });
});

test("resolvePaymentCore: rejects a reservation that isn't pending", async () => {
	const email = "test-checkout-resolve-notpending@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const created = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-09-20",
		checkOut: "2026-09-22",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	await db
		.update(reservation)
		.set({ status: "paid" })
		.where(eq(reservation.id, created.reservationId));

	const result = await resolvePaymentCore(
		requestHeaders,
		created.reservationId,
		"success",
	);
	expect(result).toEqual({ success: false, error: "NOT_PENDING" });
});

test("resolvePaymentCore: transitions to payment_failed on a failure outcome", async () => {
	const email = "test-checkout-resolve-failure@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const created = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-10-01",
		checkOut: "2026-10-03",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	const result = await resolvePaymentCore(
		requestHeaders,
		created.reservationId,
		"failure",
	);
	expect(result).toEqual({ success: true, status: "payment_failed" });

	const [row] = await db
		.select()
		.from(reservation)
		.where(eq(reservation.id, created.reservationId));
	expect(row.status).toBe("payment_failed");
});

test("resolvePaymentCore: transitions to paid on a success outcome with no conflicting paid reservation", async () => {
	const email = "test-checkout-resolve-success@example.com";
	testEmails.push(email);
	const requestHeaders = await signUpAndGetCookieHeaders(email);

	const created = await createReservationCore(requestHeaders, {
		roomId,
		checkIn: "2026-10-05",
		checkOut: "2026-10-08",
		guestCount: 1,
	});
	expect(created.success).toBe(true);
	if (!created.success) throw new Error("expected success");

	const result = await resolvePaymentCore(
		requestHeaders,
		created.reservationId,
		"success",
	);
	expect(result).toEqual({ success: true, status: "paid" });

	const [row] = await db
		.select()
		.from(reservation)
		.where(eq(reservation.id, created.reservationId));
	expect(row.status).toBe("paid");
});

test("resolvePaymentCore: transitions to payment_failed on a success outcome when another paid reservation overlaps the same room's dates", async () => {
	// This is the race-condition mitigation checkout.ts's resolvePaymentCore
	// doc comment describes: the gateway reports "success" for a reservation
	// whose dates another `paid` reservation on the same room already holds
	// (set up here by inserting that competitor directly, since
	// createReservationCore's own courtesy check would otherwise refuse to
	// create it) — the in-transaction re-check must win over the gateway's
	// outcome and downgrade this one to `payment_failed` instead of `paid`.
	const holderEmail = "test-checkout-resolve-conflict-holder@example.com";
	const competitorEmail =
		"test-checkout-resolve-conflict-competitor@example.com";
	testEmails.push(holderEmail, competitorEmail);

	const holderHeaders = await signUpAndGetCookieHeaders(holderEmail);
	const held = await createReservationCore(holderHeaders, {
		roomId,
		checkIn: "2026-12-01",
		checkOut: "2026-12-05",
		guestCount: 1,
	});
	expect(held.success).toBe(true);
	if (!held.success) throw new Error("expected success");

	const competitorHeaders = await signUpAndGetCookieHeaders(competitorEmail);
	const competitorUser = await getCurrentUser(competitorHeaders);
	if (!competitorUser) throw new Error("expected a signed-in competitor");

	// Overlaps [2026-12-01, 2026-12-05): competitor.checkIn (12-03) is before
	// held.checkOut (12-05), and competitor.checkOut (12-07) is after
	// held.checkIn (12-01) — the same exclusive-checkout overlap predicate
	// resolvePaymentCore itself uses.
	await db.insert(reservation).values({
		roomId,
		guestId: competitorUser.id,
		checkIn: "2026-12-03",
		checkOut: "2026-12-07",
		guestCount: 1,
		status: "paid",
	});

	const result = await resolvePaymentCore(
		holderHeaders,
		held.reservationId,
		"success",
	);
	expect(result).toEqual({ success: true, status: "payment_failed" });

	const [row] = await db
		.select()
		.from(reservation)
		.where(eq(reservation.id, held.reservationId));
	expect(row.status).toBe("payment_failed");
});
