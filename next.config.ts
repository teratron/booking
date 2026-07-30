import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const nextConfig: NextConfig = {
	experimental: {
		// TypeScript 7's native compiler doesn't expose the classic compiler API
		// Next.js otherwise uses; this opts into the TS-CLI-based path instead.
		useTypeScriptCli: true,
	},
};

const withNextIntl = createNextIntlPlugin();

export default withNextIntl(nextConfig);
