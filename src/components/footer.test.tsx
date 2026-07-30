import { expect, test } from "vitest";
import messages from "../../messages/ru.json";

// Footer is an async Server Component — see header.test.tsx for why it isn't
// rendered here; rendering is proven via a live dev-server request instead.
test("ru catalog resolves every key Footer references", () => {
	expect(messages.Footer.navLabel).toBeTruthy();
	expect(messages.Footer.about).toBeTruthy();
	expect(messages.Footer.privacyPolicy).toBeTruthy();
	expect(messages.Footer.addHotel).toBeTruthy();
});
