import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, expect, test, vi } from "vitest";
import { GuestCountPicker } from "./guest-count-picker";

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

const LABELS = {
	label: "Гости",
	decreaseLabel: "Уменьшить",
	increaseLabel: "Увеличить",
};

test("clamps to the minimum — the decrease control is disabled at min", async () => {
	const onChange = vi.fn();
	render(<GuestCountPicker value={1} onChange={onChange} {...LABELS} />);

	const decrease = screen.getByRole("button", {
		name: LABELS.decreaseLabel,
	}) as HTMLButtonElement;
	expect(decrease.disabled).toBe(true);

	await userEvent.click(decrease);
	expect(onChange).not.toHaveBeenCalled();
});

test("increase calls onChange with value + 1, a plain controlled interface", async () => {
	const onChange = vi.fn();
	render(<GuestCountPicker value={2} onChange={onChange} {...LABELS} />);

	await userEvent.click(
		screen.getByRole("button", { name: LABELS.increaseLabel }),
	);
	expect(onChange).toHaveBeenCalledWith(3);
});

test("clamps to the maximum — the increase control is disabled at max", async () => {
	const onChange = vi.fn();
	render(
		<GuestCountPicker value={5} onChange={onChange} max={5} {...LABELS} />,
	);

	const increase = screen.getByRole("button", {
		name: LABELS.increaseLabel,
	}) as HTMLButtonElement;
	expect(increase.disabled).toBe(true);
});
