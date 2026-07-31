"use client";

import { NumberField, RecordField, ReferenceField } from "@/components";
import { ModeratedShow } from "./moderated-show";

export default function ReviewShow() {
	return (
		<ModeratedShow>
			<RecordField source="id" />
			<RecordField source="hotelId" label="Hotel">
				<ReferenceField source="hotelId" reference="hotel" />
			</RecordField>
			<RecordField source="guestId" label="Guest id" />
			<RecordField source="rating">
				<NumberField source="rating" />
			</RecordField>
			<RecordField source="comment" />
		</ModeratedShow>
	);
}
