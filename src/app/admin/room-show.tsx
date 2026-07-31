"use client";

import { NumberField, RecordField, ReferenceField } from "@/components";
import { ModeratedShow } from "./moderated-show";

export default function RoomShow() {
	return (
		<ModeratedShow>
			<RecordField source="id" />
			<RecordField source="hotelId" label="Hotel">
				<ReferenceField source="hotelId" reference="hotel" />
			</RecordField>
			<RecordField source="name" />
			<RecordField source="bedConfiguration" label="Bed configuration" />
			<RecordField source="guestCapacity" label="Guest capacity">
				<NumberField source="guestCapacity" />
			</RecordField>
			<RecordField source="basePrice" label="Base price">
				<NumberField source="basePrice" />
			</RecordField>
			<RecordField
				source="featureTags"
				label="Feature tags"
				render={(record) =>
					Array.isArray(record.featureTags)
						? record.featureTags.join(", ")
						: null
				}
			/>
		</ModeratedShow>
	);
}
