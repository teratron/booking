import { StarIcon } from "lucide-react";
import Image from "next/image";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { ArticleCard } from "@/app/(marketing)/blog/article-card";
import { LeafletMapLoader } from "@/components/leaflet-map-loader";
import { Badge } from "@/components/ui/badge";
import type { ArticleSummary } from "@/lib/content/article-query";
import { getHotelNews } from "@/lib/content/article-query";
import { formatDate } from "@/lib/format-date";
import type {
	HotelProfile,
	HotelProfileAmenity,
	HotelProfileReview,
	HotelProfileRoom,
} from "@/lib/hotel-profile/hotel-profile-query";
import { getHotelProfile } from "@/lib/hotel-profile/hotel-profile-query";
import { RecentlyViewedRail } from "./recently-viewed";
import { type RoomBookingLabels, RoomDetailDialog } from "./room-detail-dialog";

const HEADER_AMENITY_BADGE_LIMIT = 6;
const PROFILE_MAP_ZOOM = 14;

export default async function HotelProfilePage({
	params,
}: {
	params: Promise<{ id: string }>;
}) {
	const { id } = await params;
	const [profile, news] = await Promise.all([
		getHotelProfile(id),
		getHotelNews(id),
	]);
	if (!profile) notFound();

	const t = await getTranslations("HotelProfile");
	const resultCardT = await getTranslations("ResultCard");
	const roomBookingT = await getTranslations("RoomBooking");
	const roomBookingLabels: RoomBookingLabels = {
		triggerLabel: roomBookingT("triggerLabel"),
		datesPlaceholder: roomBookingT("datesPlaceholder"),
		guestsLabel: roomBookingT("guestsLabel"),
		decreaseGuestsLabel: roomBookingT("decreaseGuestsLabel"),
		increaseGuestsLabel: roomBookingT("increaseGuestsLabel"),
		amenitiesTitle: roomBookingT("amenitiesTitle"),
		bedConfigurationPrefix: roomBookingT("bedConfigurationPrefix"),
		checkingLabel: roomBookingT("checkingLabel"),
		availableLabel: roomBookingT("availableLabel"),
		unavailableLabel: roomBookingT("unavailableLabel"),
		reserveLabel: roomBookingT("reserveLabel"),
		reservingLabel: roomBookingT("reservingLabel"),
		successTitle: roomBookingT("successTitle"),
		successBody: roomBookingT("successBody"),
		successLinkLabel: roomBookingT("successLinkLabel"),
		signInLinkLabel: roomBookingT("signInLinkLabel"),
		errorMessages: {
			UNAUTHENTICATED: roomBookingT("errorUnauthenticatedLabel"),
			VALIDATION_ERROR: roomBookingT("errorValidationLabel"),
			ROOM_NOT_FOUND: roomBookingT("errorRoomNotFoundLabel"),
			CAPACITY_EXCEEDED: roomBookingT("errorCapacityExceededLabel"),
			UNAVAILABLE: roomBookingT("errorUnavailableLabel"),
		},
	};

	return (
		<main className="mx-auto max-w-5xl space-y-8 px-4 py-10">
			<Gallery photos={profile.galleryPhotos} hotelName={profile.name} />
			<Header
				profile={profile}
				noReviewsLabel={resultCardT("noReviewsLabel")}
			/>
			<RoomsSection
				rooms={profile.rooms}
				title={t("roomsTitle")}
				guestsLabel={(count) => t("guestsLabel", { count })}
				fromLabel={resultCardT("fromLabel")}
				bookingLabels={roomBookingLabels}
			/>
			<section className="space-y-3">
				<h2 className="text-xl font-medium">{t("locationTitle")}</h2>
				<LeafletMapLoader
					pins={[
						{
							id: profile.id,
							name: profile.name,
							latitude: profile.latitude,
							longitude: profile.longitude,
						},
					]}
					center={[profile.latitude, profile.longitude]}
					zoom={PROFILE_MAP_ZOOM}
				/>
			</section>
			<ServicesSection
				amenities={profile.amenities}
				title={t("servicesTitle")}
			/>
			<NewsSection articles={news} title={t("newsTitle")} />
			<ReviewsSection reviews={profile.reviews} title={t("reviewsTitle")} />
			<RecentlyViewedRail
				currentHotel={{ id: profile.id, name: profile.name }}
				title={t("recentlyViewedTitle")}
			/>
		</main>
	);
}

function Gallery({
	photos,
	hotelName,
}: {
	photos: string[];
	hotelName: string;
}) {
	if (photos.length === 0) return null;
	return (
		<div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
			{photos.map((url) => (
				<div
					key={url}
					className="relative aspect-square overflow-hidden rounded-lg bg-muted"
				>
					<Image
						src={url}
						alt={hotelName}
						fill
						sizes="(min-width: 640px) 25vw, 50vw"
						className="object-cover"
					/>
				</div>
			))}
		</div>
	);
}

function Header({
	profile,
	noReviewsLabel,
}: {
	profile: HotelProfile;
	noReviewsLabel: string;
}) {
	return (
		<div className="space-y-2">
			<div className="flex flex-wrap items-start justify-between gap-2">
				<h1 className="text-3xl font-semibold">{profile.name}</h1>
				{profile.starCategory ? (
					<span className="flex shrink-0 items-center gap-1 text-sm text-muted-foreground">
						{profile.starCategory}
						<StarIcon className="size-4 fill-current" />
					</span>
				) : null}
			</div>
			<p className="text-muted-foreground">{profile.address}</p>
			<p className="text-sm text-muted-foreground">
				{profile.reviewCount > 0
					? `${profile.avgRating?.toFixed(1)} (${profile.reviewCount})`
					: noReviewsLabel}
			</p>
			{profile.amenities.length > 0 ? (
				<div className="flex flex-wrap gap-1">
					{profile.amenities
						.slice(0, HEADER_AMENITY_BADGE_LIMIT)
						.map((amenity) => (
							<Badge key={amenity.id} variant="secondary">
								{amenity.name}
							</Badge>
						))}
				</div>
			) : null}
		</div>
	);
}

function RoomsSection({
	rooms,
	title,
	guestsLabel,
	fromLabel,
	bookingLabels,
}: {
	rooms: HotelProfileRoom[];
	title: string;
	guestsLabel: (count: number) => string;
	fromLabel: string;
	bookingLabels: RoomBookingLabels;
}) {
	if (rooms.length === 0) return null;
	return (
		<section className="space-y-3">
			<h2 className="text-xl font-medium">{title}</h2>
			<div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
				{rooms.map((room) => (
					<div key={room.id} className="overflow-hidden rounded-lg border">
						<div className="relative aspect-4/3 w-full bg-muted">
							{room.coverPhotoUrl ? (
								<Image
									src={room.coverPhotoUrl}
									alt={room.name}
									fill
									sizes="(min-width: 768px) 33vw, 100vw"
									className="object-cover"
								/>
							) : null}
						</div>
						<div className="space-y-2 p-3">
							<p className="font-medium">{room.name}</p>
							<p className="text-sm text-muted-foreground">
								{guestsLabel(room.guestCapacity)}
							</p>
							<p className="text-sm font-medium">
								{fromLabel} {room.basePrice} ₴
							</p>
							<RoomDetailDialog
								room={room}
								capacityLabel={guestsLabel(room.guestCapacity)}
								priceLabel={`${fromLabel} ${room.basePrice} ₴`}
								labels={bookingLabels}
							/>
						</div>
					</div>
				))}
			</div>
		</section>
	);
}

function ServicesSection({
	amenities,
	title,
}: {
	amenities: HotelProfileAmenity[];
	title: string;
}) {
	if (amenities.length === 0) return null;
	return (
		<section className="space-y-3">
			<h2 className="text-xl font-medium">{title}</h2>
			<ul className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
				{amenities.map((amenity) => (
					<li key={amenity.id}>{amenity.name}</li>
				))}
			</ul>
		</section>
	);
}

function NewsSection({
	articles,
	title,
}: {
	articles: ArticleSummary[];
	title: string;
}) {
	if (articles.length === 0) return null;
	return (
		<section className="space-y-3">
			<h2 className="text-xl font-medium">{title}</h2>
			<div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
				{articles.map((article) => (
					<ArticleCard key={article.id} article={article} />
				))}
			</div>
		</section>
	);
}

function ReviewsSection({
	reviews,
	title,
}: {
	reviews: HotelProfileReview[];
	title: string;
}) {
	if (reviews.length === 0) return null;
	return (
		<section className="space-y-3">
			<h2 className="text-xl font-medium">{title}</h2>
			<ul className="space-y-4">
				{reviews.map((review) => (
					<li key={review.id} className="space-y-1 border-b pb-4 last:border-0">
						<div className="flex items-center gap-2">
							{review.guestAvatar ? (
								<Image
									src={review.guestAvatar}
									alt={review.guestName}
									width={32}
									height={32}
									className="rounded-full"
								/>
							) : null}
							<span className="font-medium">{review.guestName}</span>
							<span className="text-sm text-muted-foreground">
								{review.rating} / 5
							</span>
						</div>
						<p className="text-sm">{review.comment}</p>
						<p className="text-xs text-muted-foreground">
							{formatDate(review.createdAt)}
						</p>
					</li>
				))}
			</ul>
		</section>
	);
}
