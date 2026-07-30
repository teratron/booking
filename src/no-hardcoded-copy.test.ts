import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { expect, test } from "vitest";

const CYRILLIC = /[Ѐ-ӿ]/;
const SCAN_ROOTS = ["src/app", "src/components"];

function collectSourceFiles(root: string): string[] {
	return readdirSync(root, { recursive: true, withFileTypes: true })
		.filter((entry) => entry.isFile())
		.filter((entry) => /\.(ts|tsx)$/.test(entry.name))
		.filter((entry) => !entry.name.includes(".test."))
		.map((entry) => join(entry.parentPath, entry.name));
}

test("no literal Cyrillic string is hardcoded outside the message catalog", () => {
	const offenders = SCAN_ROOTS.flatMap(collectSourceFiles).filter((file) =>
		CYRILLIC.test(readFileSync(file, "utf-8")),
	);

	expect(offenders).toEqual([]);
});
