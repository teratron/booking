import { expect, test } from "vitest";
import messages from "../../messages/ru.json";

// LanguageSwitcher is an async Server Component — see header.test.tsx for why
// it isn't rendered here; rendering is proven via a live dev-server request.
test("ru catalog resolves every key LanguageSwitcher references", () => {
	expect(messages.LanguageSwitcher.current).toBeTruthy();
	expect(messages.LanguageSwitcher.ariaLabel).toBeTruthy();
});
