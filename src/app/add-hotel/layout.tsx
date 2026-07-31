import { Footer } from "@/components/footer";
import { Header } from "@/components/header";

export default function AddHotelLayout({
	children,
}: Readonly<{
	children: React.ReactNode;
}>) {
	return (
		<>
			<Header />
			{children}
			<Footer />
		</>
	);
}
