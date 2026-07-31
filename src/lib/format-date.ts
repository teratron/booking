export function formatDate(date: Date): string {
	return new Date(date).toLocaleDateString("ru-RU", {
		year: "numeric",
		month: "long",
		day: "numeric",
	});
}
