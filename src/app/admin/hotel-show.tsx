"use client";

import { RecordField } from "@/components";
import { ModeratedShow } from "./moderated-show";

export default function HotelShow() {
	return (
		<ModeratedShow>
			<RecordField source="id" />
			<RecordField source="ownerId" label="Owner id" />
			<RecordField source="name" />
			<RecordField source="starCategory" label="Star category" />
			<RecordField source="address" />
			<RecordField source="latitude" />
			<RecordField source="longitude" />
			<RecordField source="phone" />
		</ModeratedShow>
	);
}
