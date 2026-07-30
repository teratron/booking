import type { NextConfig } from "next";

const nextConfig: NextConfig = {
	experimental: {
		// TypeScript 7's native compiler doesn't expose the classic compiler API
		// Next.js otherwise uses; this opts into the TS-CLI-based path instead.
		useTypeScriptCli: true,
	},
};

export default nextConfig;
