import { relations } from "drizzle-orm";
import {
	boolean,
	date,
	doublePrecision,
	integer,
	numeric,
	pgEnum,
	pgTable,
	primaryKey,
	text,
	timestamp,
	uuid,
} from "drizzle-orm/pg-core";

// --- Shared enums ---------------------------------------------------------

export const actorRoleEnum = pgEnum("actor_role", ["guest", "owner", "admin"]);

export const moderationStatusEnum = pgEnum("moderation_status", [
	"pending",
	"published",
	"rejected",
]);

export const reservationStatusEnum = pgEnum("reservation_status", [
	"pending",
	"paid",
	"payment_failed",
	"cancelled",
]);

export const amenityGroupEnum = pgEnum("amenity_group", [
	"hotel",
	"room",
	"bathroom",
	"bedroom",
	"general",
]);

export const mediaTypeEnum = pgEnum("media_type", ["photo", "video"]);

// l1-hotel-discovery.md §5.2 lists "accommodation type" as a required
// catalog filter facet; Phase 1's schema had no field for it. Added in
// Phase 4 (T-4A01) rather than Phase 1 because no discovery/catalog work
// existed yet to consume it.
export const accommodationTypeEnum = pgEnum("accommodation_type", [
	"hotel",
	"hostel",
	"apartment",
	"guesthouse",
	"resort",
]);

// --- Shared column groups (spread into tables below to avoid repeating ---
// --- the same timestamp / moderation shape on every table). --------------

const timestamps = {
	createdAt: timestamp("created_at").notNull().defaultNow(),
	updatedAt: timestamp("updated_at").notNull().defaultNow(),
};

const moderation = {
	status: moderationStatusEnum("status").notNull().default("pending"),
	moderationReason: text("moderation_reason"),
};

// --- Identity (Better Auth core tables — table/column names match its ----
// --- Drizzle adapter exactly, so Phase 2 adopts this schema as-is) -------

export const user = pgTable("user", {
	id: text("id").primaryKey(),
	name: text("name").notNull(),
	email: text("email").notNull().unique(),
	emailVerified: boolean("email_verified").notNull().default(false),
	image: text("image"),
	role: actorRoleEnum("role").notNull().default("guest"),
	...timestamps,
});

export const session = pgTable("session", {
	id: text("id").primaryKey(),
	userId: text("user_id")
		.notNull()
		.references(() => user.id, { onDelete: "cascade" }),
	token: text("token").notNull().unique(),
	expiresAt: timestamp("expires_at").notNull(),
	ipAddress: text("ip_address"),
	userAgent: text("user_agent"),
	...timestamps,
});

export const account = pgTable("account", {
	id: text("id").primaryKey(),
	userId: text("user_id")
		.notNull()
		.references(() => user.id, { onDelete: "cascade" }),
	accountId: text("account_id").notNull(),
	providerId: text("provider_id").notNull(),
	accessToken: text("access_token"),
	refreshToken: text("refresh_token"),
	accessTokenExpiresAt: timestamp("access_token_expires_at"),
	refreshTokenExpiresAt: timestamp("refresh_token_expires_at"),
	scope: text("scope"),
	idToken: text("id_token"),
	password: text("password"),
	...timestamps,
});

export const verification = pgTable("verification", {
	id: text("id").primaryKey(),
	identifier: text("identifier").notNull(),
	value: text("value").notNull(),
	expiresAt: timestamp("expires_at").notNull(),
	...timestamps,
});

// --- Shared amenity taxonomy (one taxonomy, reused verbatim by hotel and -
// --- room submission per l1-property-onboarding.md §6.2) -----------------

export const amenity = pgTable("amenity", {
	id: uuid("id").primaryKey().defaultRandom(),
	name: text("name").notNull(),
	group: amenityGroupEnum("group").notNull(),
	icon: text("icon"),
});

export const hotelAmenity = pgTable(
	"hotel_amenity",
	{
		hotelId: uuid("hotel_id")
			.notNull()
			.references(() => hotel.id, { onDelete: "cascade" }),
		amenityId: uuid("amenity_id")
			.notNull()
			.references(() => amenity.id, { onDelete: "cascade" }),
	},
	(table) => [primaryKey({ columns: [table.hotelId, table.amenityId] })],
);

export const roomAmenity = pgTable(
	"room_amenity",
	{
		roomId: uuid("room_id")
			.notNull()
			.references(() => room.id, { onDelete: "cascade" }),
		amenityId: uuid("amenity_id")
			.notNull()
			.references(() => amenity.id, { onDelete: "cascade" }),
	},
	(table) => [primaryKey({ columns: [table.roomId, table.amenityId] })],
);

// --- Hotel -----------------------------------------------------------------

export const hotel = pgTable("hotel", {
	id: uuid("id").primaryKey().defaultRandom(),
	ownerId: text("owner_id")
		.notNull()
		.references(() => user.id),
	name: text("name").notNull(),
	starCategory: integer("star_category"),
	accommodationType: accommodationTypeEnum("accommodation_type")
		.notNull()
		.default("hotel"),
	address: text("address").notNull(),
	latitude: doublePrecision("latitude").notNull(),
	longitude: doublePrecision("longitude").notNull(),
	phone: text("phone").notNull(),
	...moderation,
	...timestamps,
});

export const hotelMedia = pgTable("hotel_media", {
	id: uuid("id").primaryKey().defaultRandom(),
	hotelId: uuid("hotel_id")
		.notNull()
		.references(() => hotel.id, { onDelete: "cascade" }),
	url: text("url").notNull(),
	type: mediaTypeEnum("type").notNull(),
	sortOrder: integer("sort_order").notNull().default(0),
	createdAt: timestamp("created_at").notNull().defaultNow(),
});

// --- Room — hotelId is a required FK with no nullable variant, per the ---
// --- l1-platform-foundation.md hotel/room hierarchy invariant. -----------

export const room = pgTable("room", {
	id: uuid("id").primaryKey().defaultRandom(),
	hotelId: uuid("hotel_id")
		.notNull()
		.references(() => hotel.id, { onDelete: "cascade" }),
	name: text("name").notNull(),
	bedConfiguration: text("bed_configuration"),
	guestCapacity: integer("guest_capacity").notNull(),
	basePrice: numeric("base_price", { precision: 10, scale: 2 }).notNull(),
	featureTags: text("feature_tags").array(),
	...moderation,
	...timestamps,
});

export const roomMedia = pgTable("room_media", {
	id: uuid("id").primaryKey().defaultRandom(),
	roomId: uuid("room_id")
		.notNull()
		.references(() => room.id, { onDelete: "cascade" }),
	url: text("url").notNull(),
	sortOrder: integer("sort_order").notNull().default(0),
	createdAt: timestamp("created_at").notNull().defaultNow(),
});

// --- Review ------------------------------------------------------------

export const review = pgTable("review", {
	id: uuid("id").primaryKey().defaultRandom(),
	hotelId: uuid("hotel_id")
		.notNull()
		.references(() => hotel.id, { onDelete: "cascade" }),
	guestId: text("guest_id")
		.notNull()
		.references(() => user.id),
	rating: integer("rating").notNull(),
	comment: text("comment").notNull(),
	...moderation,
	...timestamps,
});

// --- Article — admin-authored, optionally hotel-scoped; skips the --------
// --- moderation checkpoint since an admin publishing it is already the ---
// --- trusted-actor act (l1-content-publishing.md §3). ---------------------

export const article = pgTable("article", {
	id: uuid("id").primaryKey().defaultRandom(),
	authorId: text("author_id")
		.notNull()
		.references(() => user.id),
	hotelId: uuid("hotel_id").references(() => hotel.id, {
		onDelete: "set null",
	}),
	title: text("title").notNull(),
	coverImage: text("cover_image").notNull(),
	summary: text("summary").notNull(),
	content: text("content").notNull(),
	publishedAt: timestamp("published_at").notNull().defaultNow(),
	...timestamps,
});

// --- Reservation — a room can have many overlapping-in-time attempts but -
// --- only one may hold `paid`; overlap validation is application logic ---
// --- over this table, not a separate availability calendar. --------------

export const reservation = pgTable("reservation", {
	id: uuid("id").primaryKey().defaultRandom(),
	roomId: uuid("room_id")
		.notNull()
		.references(() => room.id),
	guestId: text("guest_id")
		.notNull()
		.references(() => user.id),
	checkIn: date("check_in").notNull(),
	checkOut: date("check_out").notNull(),
	guestCount: integer("guest_count").notNull(),
	status: reservationStatusEnum("status").notNull().default("pending"),
	paymentReference: text("payment_reference"),
	...timestamps,
});

// --- Relations (query ergonomics only — constraints above are what -------
// --- actually enforces the hierarchy/moderation invariants at the DB -----
// --- level). ---------------------------------------------------------------

export const userRelations = relations(user, ({ many }) => ({
	hotels: many(hotel),
	reviews: many(review),
	reservations: many(reservation),
	articles: many(article),
}));

export const hotelRelations = relations(hotel, ({ one, many }) => ({
	owner: one(user, { fields: [hotel.ownerId], references: [user.id] }),
	rooms: many(room),
	media: many(hotelMedia),
	reviews: many(review),
	articles: many(article),
	amenities: many(hotelAmenity),
}));

export const roomRelations = relations(room, ({ one, many }) => ({
	hotel: one(hotel, { fields: [room.hotelId], references: [hotel.id] }),
	media: many(roomMedia),
	reservations: many(reservation),
	amenities: many(roomAmenity),
}));

export const reviewRelations = relations(review, ({ one }) => ({
	hotel: one(hotel, { fields: [review.hotelId], references: [hotel.id] }),
	guest: one(user, { fields: [review.guestId], references: [user.id] }),
}));

export const articleRelations = relations(article, ({ one }) => ({
	author: one(user, { fields: [article.authorId], references: [user.id] }),
	hotel: one(hotel, { fields: [article.hotelId], references: [hotel.id] }),
}));

export const reservationRelations = relations(reservation, ({ one }) => ({
	room: one(room, { fields: [reservation.roomId], references: [room.id] }),
	guest: one(user, { fields: [reservation.guestId], references: [user.id] }),
}));

export const amenityRelations = relations(amenity, ({ many }) => ({
	hotels: many(hotelAmenity),
	rooms: many(roomAmenity),
}));

export const hotelAmenityRelations = relations(hotelAmenity, ({ one }) => ({
	hotel: one(hotel, { fields: [hotelAmenity.hotelId], references: [hotel.id] }),
	amenity: one(amenity, {
		fields: [hotelAmenity.amenityId],
		references: [amenity.id],
	}),
}));

export const roomAmenityRelations = relations(roomAmenity, ({ one }) => ({
	room: one(room, { fields: [roomAmenity.roomId], references: [room.id] }),
	amenity: one(amenity, {
		fields: [roomAmenity.amenityId],
		references: [amenity.id],
	}),
}));
