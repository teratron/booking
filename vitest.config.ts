import react from "@vitejs/plugin-react";
import "dotenv/config";
import tsconfigPaths from "vite-tsconfig-paths";
import { defineConfig } from "vitest/config";

export default defineConfig({
	plugins: [tsconfigPaths(), react()],
	test: {
		environment: "jsdom",
		// The suite hits a real Postgres instance from every test file, and
		// each file's dynamic-import setup competes for CPU when ~25+ files
		// run concurrently — the 5s default occasionally isn't enough headroom
		// under that load, not a stuck test. Discovered live in Phase 3
		// (T-3B02) via a test that passed instantly in isolation but timed
		// out only as part of the full run.
		testTimeout: 15000,
	},
});
