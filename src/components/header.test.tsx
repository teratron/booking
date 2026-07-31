import { expect, test } from "vitest";
import messages from "../../messages/ru.json";

// Header is an async Server Component (`getTranslations`) — next-intl/server
// refuses to run under Vitest's jsdom environment ("not supported in Client
// Components"), and React's client renderer can't resolve an async
// component's returned Promise either way. Per Next.js's own Vitest guide,
// async Server Components are an E2E concern, not a unit-test one; rendering
// is proven via a live dev-server request (see T-1C01/T-1C02 Changes).
test("ru catalog resolves every key Header references", () => {
	expect(messages.Header.navLabel).toBeTruthy();
	expect(messages.Header.navCatalog).toBeTruthy();
	expect(messages.Header.navMap).toBeTruthy();
	expect(messages.Header.navBlog).toBeTruthy();
	expect(messages.Header.signInLabel).toBeTruthy();
	expect(messages.Header.signOutLabel).toBeTruthy();
	expect(messages.Header.signOutPendingLabel).toBeTruthy();
});
