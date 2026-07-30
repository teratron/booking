import { expect, test } from "vitest";
import messages from "../../../../messages/ru.json";

// PrivacyPolicyPage is an async Server Component — see header.test.tsx for
// why it isn't rendered here; rendering is proven via a live dev-server
// request (T-1C03/T-1T02 phase record).
test("ru catalog resolves every key PrivacyPolicyPage references", () => {
	expect(messages.PrivacyPolicy.title).toBeTruthy();
	expect(messages.PrivacyPolicy.body).toBeTruthy();
});
