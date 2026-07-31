"use client";

import { Resource } from "ra-core";
import simpleRestProvider from "ra-data-simple-rest";
import { Admin, EditGuesser, ListGuesser, ShowGuesser } from "@/components";
import HotelShow from "./hotel-show";
import ReviewShow from "./review-show";
import RoomShow from "./room-show";

const dataProvider = simpleRestProvider("/api/admin");

export default function App() {
	return (
		<Admin dataProvider={dataProvider}>
			<Resource
				name="hotel"
				list={ListGuesser}
				show={HotelShow}
				edit={EditGuesser}
			/>
			<Resource
				name="room"
				list={ListGuesser}
				show={RoomShow}
				edit={EditGuesser}
			/>
			<Resource
				name="review"
				list={ListGuesser}
				show={ReviewShow}
				edit={EditGuesser}
			/>
			<Resource
				name="article"
				list={ListGuesser}
				show={ShowGuesser}
				edit={EditGuesser}
			/>
		</Admin>
	);
}
