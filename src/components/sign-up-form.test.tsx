import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, expect, test, vi } from "vitest";
import { SignUpForm } from "./sign-up-form";

const push = vi.fn();
const refresh = vi.fn();
vi.mock("next/navigation", () => ({
	useRouter: () => ({ push, refresh }),
}));

const signUpEmail = vi.fn();
vi.mock("@/lib/auth/client", () => ({
	authClient: {
		signUp: { email: (...args: unknown[]) => signUpEmail(...args) },
	},
}));

afterEach(() => {
	cleanup();
	vi.clearAllMocks();
});

const PROPS = {
	title: "Регистрация",
	description: "Создайте аккаунт.",
	nameLabel: "Имя",
	emailLabel: "Электронная почта",
	passwordLabel: "Пароль",
	passwordHint: "От 8 до 128 символов",
	submitLabel: "Зарегистрироваться",
	submitPendingLabel: "Регистрация…",
	errorGeneric: "Не удалось создать аккаунт.",
	signInPrompt: "Уже есть аккаунт?",
	signInLink: "Войти",
};

test("submitting the sign-up form calls signUp.email with the entered fields and redirects on success", async () => {
	signUpEmail.mockImplementation(
		(_payload: unknown, callbacks: { onSuccess: () => void }) => {
			callbacks.onSuccess();
			return Promise.resolve();
		},
	);
	const user = userEvent.setup();
	render(<SignUpForm {...PROPS} />);

	await user.type(screen.getByLabelText(PROPS.nameLabel), "Иван");
	await user.type(screen.getByLabelText(PROPS.emailLabel), "ivan@example.com");
	await user.type(screen.getByLabelText(PROPS.passwordLabel), "password123");
	await user.click(screen.getByRole("button", { name: PROPS.submitLabel }));

	expect(signUpEmail).toHaveBeenCalledWith(
		{ name: "Иван", email: "ivan@example.com", password: "password123" },
		expect.objectContaining({
			onSuccess: expect.any(Function),
			onError: expect.any(Function),
		}),
	);
	expect(push).toHaveBeenCalledWith("/");
	expect(refresh).toHaveBeenCalled();
});

test("a failed sign-up shows the generic error message", async () => {
	signUpEmail.mockImplementation(
		(_payload: unknown, callbacks: { onError: () => void }) => {
			callbacks.onError();
			return Promise.resolve();
		},
	);
	const user = userEvent.setup();
	render(<SignUpForm {...PROPS} />);

	await user.type(screen.getByLabelText(PROPS.nameLabel), "Иван");
	await user.type(screen.getByLabelText(PROPS.emailLabel), "ivan@example.com");
	await user.type(screen.getByLabelText(PROPS.passwordLabel), "password123");
	await user.click(screen.getByRole("button", { name: PROPS.submitLabel }));

	expect(await screen.findByRole("alert")).toBeDefined();
	expect(screen.getByText(PROPS.errorGeneric)).toBeDefined();
	expect(push).not.toHaveBeenCalled();
});
