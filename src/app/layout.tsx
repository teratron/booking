import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { NextIntlClientProvider } from "next-intl";
import { getMessages } from "next-intl/server";
import { Footer } from "@/components/footer";
import { Header } from "@/components/header";
import "./globals.css";

const geistSans = Geist({
	variable: "--font-geist-sans",
	subsets: ["latin"],
});

const geistMono = Geist_Mono({
	variable: "--font-geist-mono",
	subsets: ["latin"],
});

export const metadata: Metadata = {
	title: "Booking",
	description: "Booking platform",
};

export default async function RootLayout({
	children,
}: Readonly<{
	children: React.ReactNode;
}>) {
	const messages = await getMessages();

	return (
		<html lang="ru" className={`${geistSans.variable} ${geistMono.variable}`}>
			<body>
				<NextIntlClientProvider messages={messages}>
					<Header />
					{children}
					<Footer />
				</NextIntlClientProvider>
			</body>
		</html>
	);
}
