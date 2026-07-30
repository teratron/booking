import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { expect, test } from "vitest";
import { FeedbackPopup } from "./feedback-popup";

const PROPS = {
	triggerLabel: "Обратная связь",
	title: "Обратная связь",
	description: "Расскажите нам, что можно улучшить.",
	nameLabel: "Имя",
	messageLabel: "Сообщение",
	submitLabel: "Отправить",
	cancelLabel: "Отмена",
};

test("feedback popup opens from its trigger and closes without navigation", async () => {
	const user = userEvent.setup();
	render(<FeedbackPopup {...PROPS} />);

	expect(screen.queryByRole("dialog")).toBeNull();

	await user.click(screen.getByRole("button", { name: PROPS.triggerLabel }));
	expect(screen.getByRole("dialog")).toBeDefined();
	expect(screen.getByText(PROPS.description)).toBeDefined();

	await user.click(screen.getByRole("button", { name: PROPS.cancelLabel }));
	expect(screen.queryByRole("dialog")).toBeNull();
});
