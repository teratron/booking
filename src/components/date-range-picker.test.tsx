import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, expect, test, vi } from "vitest";
import { DateRangePicker, type DateRangeValue } from "./date-range-picker";

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

function Wrapper({ onChange }: { onChange: (value: DateRangeValue) => void }) {
	return <DateRangePicker value={{}} onChange={onChange} placeholder="Даты" />;
}

function availableDayButtons() {
	return screen
		.getAllByRole("gridcell")
		.map((cell) => cell.querySelector("button"))
		.filter(
			(button): button is HTMLButtonElement =>
				button !== null && !button.disabled,
		);
}

test("a later-then-earlier click never produces an inverted range", async () => {
	const onChange = vi.fn();
	const user = userEvent.setup();
	render(<Wrapper onChange={onChange} />);

	await user.click(screen.getByRole("button", { name: "Даты" }));

	const available = availableDayButtons();
	expect(available.length).toBeGreaterThan(10);

	// Click a later-in-the-grid day first, then an earlier one — regardless
	// of how react-day-picker resolves the pair (re-anchor vs. treat as a
	// new single-day selection), the resulting range must never come back
	// with `to` before `from`.
	await user.click(available[10]);
	await user.click(available[5]);

	const lastCall = onChange.mock.calls.at(-1)?.[0] as DateRangeValue;
	expect(lastCall.from).toBeInstanceOf(Date);
	if (lastCall.to) {
		expect(lastCall.from?.getTime()).toBeLessThanOrEqual(lastCall.to.getTime());
	}
});

test("a plain controlled value/onChange interface — selecting a range calls onChange with both ends", async () => {
	const onChange = vi.fn();
	const user = userEvent.setup();
	render(<Wrapper onChange={onChange} />);

	await user.click(screen.getByRole("button", { name: "Даты" }));

	const available = availableDayButtons();
	await user.click(available[0]);
	await user.click(available[3]);

	const lastCall = onChange.mock.calls.at(-1)?.[0] as DateRangeValue;
	expect(lastCall.from).toBeInstanceOf(Date);
	expect(lastCall.to).toBeInstanceOf(Date);
});
