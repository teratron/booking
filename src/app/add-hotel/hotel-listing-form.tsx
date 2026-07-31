"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { upload } from "@vercel/blob/client";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { type Resolver, useFieldArray, useForm } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { amenity as amenityTable } from "@/lib/db/schema";
import {
	submitHotelListing,
	updateHotelListing,
} from "@/lib/property-onboarding/actions";
import {
	accommodationTypeValues,
	type HotelListingInput,
	hotelListingInputSchema,
} from "@/lib/property-onboarding/schema";

type Amenity = typeof amenityTable.$inferSelect;

const ROOM_AMENITY_GROUPS = ["room", "bathroom", "bedroom", "general"] as const;

function FieldError({ message }: { message?: string }) {
	if (!message) return null;
	return <p className="text-sm text-destructive">{message}</p>;
}

const emptyRoom: HotelListingInput["rooms"][number] = {
	name: "",
	guestCapacity: 1,
	basePrice: 0,
	amenityIds: [],
	mediaUrls: [],
};

const defaultFormValues: HotelListingInput = {
	name: "",
	accommodationType: "hotel",
	address: "",
	latitude: 0,
	longitude: 0,
	phone: "",
	amenityIds: [],
	media: [],
	rooms: [emptyRoom],
};

function AmenityCheckboxGroup({
	amenities,
	groups,
	groupLabel,
	value,
	onChange,
}: {
	amenities: Amenity[];
	groups: readonly string[];
	groupLabel: (group: string) => string;
	value: string[];
	onChange: (next: string[]) => void;
}) {
	function toggle(id: string) {
		onChange(
			value.includes(id) ? value.filter((v) => v !== id) : [...value, id],
		);
	}

	return (
		<div className="space-y-3">
			{groups.map((group) => {
				const items = amenities.filter((item) => item.group === group);
				if (items.length === 0) return null;
				return (
					<div key={group}>
						<p className="mb-1.5 text-sm font-medium">{groupLabel(group)}</p>
						<div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
							{items.map((item) => (
								<label
									key={item.id}
									htmlFor={`amenity-${item.id}`}
									className="flex items-center gap-2 text-sm"
								>
									<Checkbox
										id={`amenity-${item.id}`}
										checked={value.includes(item.id)}
										onCheckedChange={() => toggle(item.id)}
									/>
									{item.name}
								</label>
							))}
						</div>
					</div>
				);
			})}
		</div>
	);
}

/**
 * Uploads directly to Vercel Blob via T-3B01's client-upload token route —
 * the browser never sends file bytes through this app's own server. The data
 * shape (media: {url,type}[]) is unchanged from T-3B02's placeholder.
 */
function HotelMediaEditor({
	value,
	onChange,
	inputLabel,
	uploadingLabel,
	errorLabel,
	photoLabel,
	videoLabel,
	removeLabel,
}: {
	value: HotelListingInput["media"];
	onChange: (next: HotelListingInput["media"]) => void;
	inputLabel: string;
	uploadingLabel: string;
	errorLabel: string;
	photoLabel: string;
	videoLabel: string;
	removeLabel: string;
}) {
	const [uploading, setUploading] = useState(false);
	const [error, setError] = useState(false);

	async function handleFiles(files: FileList | null) {
		if (!files || files.length === 0) return;
		setError(false);
		setUploading(true);
		try {
			const uploaded = await Promise.all(
				Array.from(files).map((file) =>
					upload(file.name, file, {
						access: "public",
						handleUploadUrl: "/api/upload",
					}),
				),
			);
			onChange([
				...(value ?? []),
				...uploaded.map((blob) => ({
					url: blob.url,
					type: (blob.contentType?.startsWith("video/") ? "video" : "photo") as
						| "photo"
						| "video",
				})),
			]);
		} catch {
			setError(true);
		} finally {
			setUploading(false);
		}
	}

	return (
		<div className="space-y-2">
			<input
				type="file"
				aria-label={inputLabel}
				accept="image/*,video/*"
				multiple
				disabled={uploading}
				onChange={(event) => handleFiles(event.target.files)}
				className="text-sm"
			/>
			{uploading ? (
				<p className="text-sm text-muted-foreground">{uploadingLabel}</p>
			) : null}
			{error ? <p className="text-sm text-destructive">{errorLabel}</p> : null}
			<ul className="space-y-1">
				{(value ?? []).map((item) => (
					<li
						key={item.url}
						className="flex items-center justify-between text-sm text-muted-foreground"
					>
						<span className="truncate">
							{item.url} ({item.type === "photo" ? photoLabel : videoLabel})
						</span>
						<button
							type="button"
							className="text-destructive"
							onClick={() =>
								onChange(
									(value ?? []).filter((entry) => entry.url !== item.url),
								)
							}
						>
							{removeLabel}
						</button>
					</li>
				))}
			</ul>
		</div>
	);
}

function RoomMediaEditor({
	value,
	onChange,
	inputLabel,
	uploadingLabel,
	errorLabel,
	removeLabel,
}: {
	value: string[] | undefined;
	onChange: (next: string[]) => void;
	inputLabel: string;
	uploadingLabel: string;
	errorLabel: string;
	removeLabel: string;
}) {
	const [uploading, setUploading] = useState(false);
	const [error, setError] = useState(false);

	async function handleFiles(files: FileList | null) {
		if (!files || files.length === 0) return;
		setError(false);
		setUploading(true);
		try {
			const uploaded = await Promise.all(
				Array.from(files).map((file) =>
					upload(file.name, file, {
						access: "public",
						handleUploadUrl: "/api/upload",
					}),
				),
			);
			onChange([...(value ?? []), ...uploaded.map((blob) => blob.url)]);
		} catch {
			setError(true);
		} finally {
			setUploading(false);
		}
	}

	return (
		<div className="space-y-2">
			<input
				type="file"
				aria-label={inputLabel}
				accept="image/*"
				multiple
				disabled={uploading}
				onChange={(event) => handleFiles(event.target.files)}
				className="text-sm"
			/>
			{uploading ? (
				<p className="text-sm text-muted-foreground">{uploadingLabel}</p>
			) : null}
			{error ? <p className="text-sm text-destructive">{errorLabel}</p> : null}
			<ul className="space-y-1">
				{(value ?? []).map((mediaUrl) => (
					<li
						key={mediaUrl}
						className="flex items-center justify-between text-sm text-muted-foreground"
					>
						<span className="truncate">{mediaUrl}</span>
						<button
							type="button"
							className="text-destructive"
							onClick={() =>
								onChange((value ?? []).filter((entry) => entry !== mediaUrl))
							}
						>
							{removeLabel}
						</button>
					</li>
				))}
			</ul>
		</div>
	);
}

export function HotelListingForm({
	amenities,
	hotelId,
	defaultValues,
}: {
	amenities: Amenity[];
	/** Present in edit mode (T-3C02) — submits via updateHotelListing instead. */
	hotelId?: string;
	defaultValues?: HotelListingInput;
}) {
	const t = useTranslations("AddHotelForm");
	const router = useRouter();
	const [pending, setPending] = useState(false);
	const [error, setError] = useState<string | null>(null);

	const {
		register,
		control,
		handleSubmit,
		watch,
		setValue,
		formState: { errors },
	} = useForm<HotelListingInput>({
		// The schema's z.coerce.number() fields make zodResolver's inferred
		// input type `unknown` (it accepts pre-coercion values); this form
		// always supplies real numbers via `valueAsNumber: true`, so the
		// resolver's actual runtime behavior matches HotelListingInput even
		// though the two generic signatures don't line up structurally.
		resolver: zodResolver(
			hotelListingInputSchema,
		) as Resolver<HotelListingInput>,
		defaultValues: defaultValues ?? defaultFormValues,
	});

	const { fields, append, remove } = useFieldArray({ control, name: "rooms" });
	const hotelAmenityIds = watch("amenityIds") ?? [];
	const hotelMedia = watch("media") ?? [];

	async function onSubmit(data: HotelListingInput) {
		setError(null);
		setPending(true);
		const result = hotelId
			? await updateHotelListing(hotelId, data)
			: await submitHotelListing(data);
		if (result.success) {
			router.push("/add-hotel");
			router.refresh();
			return;
		}
		setPending(false);
		setError(
			result.error === "UNAUTHENTICATED"
				? t("errorUnauthenticated")
				: result.error === "VALIDATION_ERROR"
					? t("errorValidation")
					: t("errorGeneric"),
		);
	}

	return (
		<form onSubmit={handleSubmit(onSubmit)} className="space-y-8">
			<div>
				<h1 className="text-2xl font-semibold">{t("title")}</h1>
				<p className="text-muted-foreground">{t("description")}</p>
			</div>

			<Card>
				<CardHeader>
					<CardTitle>{t("hotelSectionTitle")}</CardTitle>
				</CardHeader>
				<CardContent className="space-y-4">
					<div className="grid gap-4 sm:grid-cols-2">
						<div className="flex flex-col gap-1.5">
							<Label htmlFor="name">{t("nameLabel")}</Label>
							<Input id="name" {...register("name")} />
							<FieldError message={errors.name?.message} />
						</div>
						<div className="flex flex-col gap-1.5">
							<Label htmlFor="starCategory">{t("starCategoryLabel")}</Label>
							<Input
								id="starCategory"
								type="number"
								min={1}
								max={5}
								{...register("starCategory", {
									// valueAsNumber turns an empty (left-blank) input into
									// NaN, not undefined, which fails zod's .optional() —
									// this field is genuinely optional, so convert by hand.
									setValueAs: (value) =>
										value === "" ? undefined : Number(value),
								})}
							/>
						</div>
					</div>

					<div className="flex flex-col gap-1.5">
						<Label htmlFor="accommodationType">
							{t("accommodationTypeLabel")}
						</Label>
						<select
							id="accommodationType"
							{...register("accommodationType")}
							className="h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
						>
							{accommodationTypeValues.map((value) => (
								<option key={value} value={value}>
									{t(
										`accommodationType${value.charAt(0).toUpperCase()}${value.slice(1)}` as "accommodationTypeHotel",
									)}
								</option>
							))}
						</select>
					</div>

					<div className="flex flex-col gap-1.5">
						<Label htmlFor="address">{t("addressLabel")}</Label>
						<Input id="address" {...register("address")} />
						<FieldError message={errors.address?.message} />
					</div>

					<div className="grid gap-4 sm:grid-cols-2">
						<div className="flex flex-col gap-1.5">
							<Label htmlFor="latitude">{t("latitudeLabel")}</Label>
							<Input
								id="latitude"
								type="number"
								step="any"
								{...register("latitude", { valueAsNumber: true })}
							/>
							<FieldError message={errors.latitude?.message} />
						</div>
						<div className="flex flex-col gap-1.5">
							<Label htmlFor="longitude">{t("longitudeLabel")}</Label>
							<Input
								id="longitude"
								type="number"
								step="any"
								{...register("longitude", { valueAsNumber: true })}
							/>
							<FieldError message={errors.longitude?.message} />
						</div>
					</div>

					<div className="flex flex-col gap-1.5">
						<Label htmlFor="phone">{t("phoneLabel")}</Label>
						<Input id="phone" {...register("phone")} />
						<FieldError message={errors.phone?.message} />
					</div>

					<div>
						<p className="mb-1.5 text-sm font-medium">{t("amenitiesLabel")}</p>
						<AmenityCheckboxGroup
							amenities={amenities}
							groups={["hotel"]}
							groupLabel={() => t("amenityGroupHotel")}
							value={hotelAmenityIds}
							onChange={(next) => setValue("amenityIds", next)}
						/>
					</div>

					<div>
						<p className="mb-1.5 text-sm font-medium">{t("hotelMediaLabel")}</p>
						<HotelMediaEditor
							value={hotelMedia}
							onChange={(next) => setValue("media", next)}
							inputLabel={t("hotelMediaLabel")}
							uploadingLabel={t("mediaUploadingLabel")}
							errorLabel={t("mediaUploadErrorLabel")}
							photoLabel={t("mediaTypePhoto")}
							videoLabel={t("mediaTypeVideo")}
							removeLabel={t("removeMediaLabel")}
						/>
					</div>
				</CardContent>
			</Card>

			<div className="space-y-4">
				<h2 className="text-xl font-semibold">{t("roomsSectionTitle")}</h2>
				{fields.map((field, index) => (
					<RoomFields
						key={field.id}
						index={index}
						amenities={amenities}
						register={register}
						errors={errors}
						watch={watch}
						setValue={setValue}
						onRemove={fields.length > 1 ? () => remove(index) : undefined}
						t={t}
					/>
				))}
				<Button
					type="button"
					variant="outline"
					onClick={() => append(emptyRoom)}
				>
					{t("addRoomLabel")}
				</Button>
			</div>

			{error ? (
				<p role="alert" className="text-sm text-destructive">
					{error}
				</p>
			) : null}

			<Button type="submit" disabled={pending}>
				{pending
					? hotelId
						? t("resubmitPendingLabel")
						: t("submitPendingLabel")
					: hotelId
						? t("resubmitLabel")
						: t("submitLabel")}
			</Button>
		</form>
	);
}

function RoomFields({
	index,
	amenities,
	register,
	errors,
	watch,
	setValue,
	onRemove,
	t,
}: {
	index: number;
	amenities: Amenity[];
	register: ReturnType<typeof useForm<HotelListingInput>>["register"];
	errors: ReturnType<typeof useForm<HotelListingInput>>["formState"]["errors"];
	watch: ReturnType<typeof useForm<HotelListingInput>>["watch"];
	setValue: ReturnType<typeof useForm<HotelListingInput>>["setValue"];
	onRemove?: () => void;
	t: ReturnType<typeof useTranslations<"AddHotelForm">>;
}) {
	const amenityIds = watch(`rooms.${index}.amenityIds`) ?? [];
	const mediaUrls = watch(`rooms.${index}.mediaUrls`) ?? [];
	const roomErrors = errors.rooms?.[index];

	return (
		<Card>
			<CardHeader className="flex flex-row items-center justify-between">
				<CardTitle>{t("roomTitle", { index: index + 1 })}</CardTitle>
				{onRemove ? (
					<Button type="button" variant="ghost" onClick={onRemove}>
						{t("removeRoomLabel")}
					</Button>
				) : null}
			</CardHeader>
			<CardContent className="space-y-4">
				<div className="grid gap-4 sm:grid-cols-2">
					<div className="flex flex-col gap-1.5">
						<Label htmlFor={`rooms.${index}.name`}>{t("roomNameLabel")}</Label>
						<Input
							id={`rooms.${index}.name`}
							{...register(`rooms.${index}.name` as const)}
						/>
						<FieldError message={roomErrors?.name?.message} />
					</div>
					<div className="flex flex-col gap-1.5">
						<Label htmlFor={`rooms.${index}.bedConfiguration`}>
							{t("bedConfigurationLabel")}
						</Label>
						<Input
							id={`rooms.${index}.bedConfiguration`}
							{...register(`rooms.${index}.bedConfiguration` as const)}
						/>
					</div>
				</div>

				<div className="grid gap-4 sm:grid-cols-2">
					<div className="flex flex-col gap-1.5">
						<Label htmlFor={`rooms.${index}.guestCapacity`}>
							{t("guestCapacityLabel")}
						</Label>
						<Input
							id={`rooms.${index}.guestCapacity`}
							type="number"
							min={1}
							{...register(`rooms.${index}.guestCapacity` as const, {
								valueAsNumber: true,
							})}
						/>
						<FieldError message={roomErrors?.guestCapacity?.message} />
					</div>
					<div className="flex flex-col gap-1.5">
						<Label htmlFor={`rooms.${index}.basePrice`}>
							{t("basePriceLabel")}
						</Label>
						<Input
							id={`rooms.${index}.basePrice`}
							type="number"
							min={0}
							step="0.01"
							{...register(`rooms.${index}.basePrice` as const, {
								valueAsNumber: true,
							})}
						/>
						<FieldError message={roomErrors?.basePrice?.message} />
					</div>
				</div>

				<div className="flex flex-col gap-1.5">
					<Label htmlFor={`rooms.${index}.featureTags`}>
						{t("featureTagsLabel")}
					</Label>
					<Input
						id={`rooms.${index}.featureTags`}
						onChange={(event) =>
							setValue(
								`rooms.${index}.featureTags`,
								event.target.value
									.split(",")
									.map((tag) => tag.trim())
									.filter(Boolean),
							)
						}
					/>
				</div>

				<div>
					<p className="mb-1.5 text-sm font-medium">
						{t("roomAmenitiesLabel")}
					</p>
					<AmenityCheckboxGroup
						amenities={amenities}
						groups={ROOM_AMENITY_GROUPS}
						groupLabel={(group) =>
							t(
								`amenityGroup${group.charAt(0).toUpperCase()}${group.slice(1)}` as "amenityGroupRoom",
							)
						}
						value={amenityIds}
						onChange={(next) => setValue(`rooms.${index}.amenityIds`, next)}
					/>
				</div>

				<div>
					<p className="mb-1.5 text-sm font-medium">{t("roomMediaLabel")}</p>
					<RoomMediaEditor
						value={mediaUrls}
						onChange={(next) => setValue(`rooms.${index}.mediaUrls`, next)}
						inputLabel={t("roomMediaLabel")}
						uploadingLabel={t("mediaUploadingLabel")}
						errorLabel={t("mediaUploadErrorLabel")}
						removeLabel={t("removeMediaLabel")}
					/>
				</div>
			</CardContent>
		</Card>
	);
}
