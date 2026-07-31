import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const nextConfig: NextConfig = {
	experimental: {
		// TypeScript 7's native compiler doesn't expose the classic compiler API
		// Next.js otherwise uses; this opts into the TS-CLI-based path instead.
		useTypeScriptCli: true,
	},
	images: {
		// Hotel/room media (T-3B01/T-3B03) uploads to Vercel Blob; every store's
		// public URL lives under this suffix, so a wildcard subdomain covers any
		// store without hardcoding one store id.
		remotePatterns: [
			{ protocol: "https", hostname: "**.public.blob.vercel-storage.com" },
		],
	},
};

const withNextIntl = createNextIntlPlugin();

export default withNextIntl(nextConfig);
