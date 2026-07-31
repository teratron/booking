"use client";

import { Resource } from "ra-core";
import simpleRestProvider from "ra-data-simple-rest";
import { Admin, EditGuesser, ListGuesser, ShowGuesser } from "@/components";

const dataProvider = simpleRestProvider("/api/admin");

export default function App() {
	return (
		<Admin dataProvider={dataProvider}>
			<Resource
				name="hotel"
				list={ListGuesser}
				show={ShowGuesser}
				edit={EditGuesser}
			/>
			<Resource
				name="room"
				list={ListGuesser}
				show={ShowGuesser}
				edit={EditGuesser}
			/>
			<Resource
				name="review"
				list={ListGuesser}
				show={ShowGuesser}
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
