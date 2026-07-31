import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, expect, test, vi } from "vitest";
import { SignInForm } from "./sign-in-form";

const push = vi.fn();
const refresh = vi.fn();
vi.mock("next/navigation", () => ({
	useRouter: () => ({ push, refresh }),
}));

const signInEmail = vi.fn();
vi.mock("@/lib/auth/client", () => ({
	authClient: {
		signIn: { email: (...args: unknown[]) => signInEmail(...args) },
	},
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

const PROPS = {
	title: "Вход",
	description: "Войдите в свой аккаунт.",
	emailLabel: "Электронная почта",
	passwordLabel: "Пароль",
	submitLabel: "Войти",
	submitPendingLabel: "Вход…",
	errorGeneric: "Не удалось войти.",
	signUpPrompt: "Ещё нет аккаунта?",
	signUpLink: "Зарегистрироваться",
};

test("submitting the sign-in form calls signIn.email with the entered credentials and redirects on success", async () => {
	signInEmail.mockImplementation(
		(_payload: unknown, callbacks: { onSuccess: () => void }) => {
			callbacks.onSuccess();
			return Promise.resolve();
		},
	);
	const user = userEvent.setup();
	render(<SignInForm {...PROPS} />);

	await user.type(screen.getByLabelText(PROPS.emailLabel), "ivan@example.com");
	await user.type(screen.getByLabelText(PROPS.passwordLabel), "password123");
	await user.click(screen.getByRole("button", { name: PROPS.submitLabel }));

	expect(signInEmail).toHaveBeenCalledWith(
		{ email: "ivan@example.com", password: "password123" },
		expect.objectContaining({
			onSuccess: expect.any(Function),
			onError: expect.any(Function),
		}),
	);
	expect(push).toHaveBeenCalledWith("/");
	expect(refresh).toHaveBeenCalled();
});

test("a failed sign-in shows the generic error message", async () => {
	signInEmail.mockImplementation(
		(_payload: unknown, callbacks: { onError: () => void }) => {
			callbacks.onError();
			return Promise.resolve();
		},
	);
	const user = userEvent.setup();
	render(<SignInForm {...PROPS} />);

	await user.type(screen.getByLabelText(PROPS.emailLabel), "ivan@example.com");
	await user.type(screen.getByLabelText(PROPS.passwordLabel), "wrong-password");
	await user.click(screen.getByRole("button", { name: PROPS.submitLabel }));

	expect(await screen.findByRole("alert")).toBeDefined();
	expect(screen.getByText(PROPS.errorGeneric)).toBeDefined();
	expect(push).not.toHaveBeenCalled();
});
