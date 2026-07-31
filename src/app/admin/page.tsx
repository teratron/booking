"use client";

import dynamic from "next/dynamic";

// ssr: false is required — the Admin app pulls in react-router, which
// errors when rendered outside a browser.
const App = dynamic(() => import("./App"), {
	ssr: false,
});

export default function AdminPage() {
	return <App />;
}
