import { getRequestConfig } from "next-intl/server";

export default getRequestConfig(async () => {
	return {
		locale: "ru",
		messages: (await import("../../messages/ru.json")).default,
	};
});
