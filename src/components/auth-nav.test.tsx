import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, expect, test, vi } from "vitest";
import { AuthNav } from "./auth-nav";

const refresh = vi.fn();
vi.mock("next/navigation", () => ({
	useRouter: () => ({ refresh }),
}));

const signOut = vi.fn();
vi.mock("@/lib/auth/client", () => ({
	authClient: { signOut: (...args: unknown[]) => signOut(...args) },
}));

const LABELS = {
	signInLabel: "Войти",
	signOutLabel: "Выйти",
	signOutPendingLabel: "Выход…",
	myReservationsLabel: "Мои бронирования",
};

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

test("shows a sign-in link when unauthenticated", () => {
	render(<AuthNav authenticated={false} userLabel={null} {...LABELS} />);

	expect(screen.getByRole("link", { name: LABELS.signInLabel })).toBeDefined();
	expect(screen.queryByRole("button")).toBeNull();
});

test("shows the user and a sign-out affordance when authenticated, and signs out on click", async () => {
	signOut.mockImplementation(
		(opts: { fetchOptions: { onSuccess: () => void } }) => {
			opts.fetchOptions.onSuccess();
			return Promise.resolve();
		},
	);
	const user = userEvent.setup();
	render(
		<AuthNav authenticated userLabel="Тестовый Пользователь" {...LABELS} />,
	);

	expect(screen.getByText("Тестовый Пользователь")).toBeDefined();
	expect(screen.queryByRole("link", { name: LABELS.signInLabel })).toBeNull();
	expect(
		screen
			.getByRole("link", { name: LABELS.myReservationsLabel })
			.getAttribute("href"),
	).toBe("/account/reservations");

	await user.click(screen.getByRole("button", { name: LABELS.signOutLabel }));

	expect(signOut).toHaveBeenCalledWith(
		expect.objectContaining({
			fetchOptions: expect.objectContaining({
				onSuccess: expect.any(Function),
				onError: expect.any(Function),
			}),
		}),
	);
	expect(refresh).toHaveBeenCalled();
});
