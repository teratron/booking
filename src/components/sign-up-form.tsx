"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { authClient } from "@/lib/auth/client";

export function SignUpForm({
	title,
	description,
	nameLabel,
	emailLabel,
	passwordLabel,
	passwordHint,
	submitLabel,
	submitPendingLabel,
	errorGeneric,
	signInPrompt,
	signInLink,
}: {
	title: string;
	description: string;
	nameLabel: string;
	emailLabel: string;
	passwordLabel: string;
	passwordHint: string;
	submitLabel: string;
	submitPendingLabel: string;
	errorGeneric: string;
	signInPrompt: string;
	signInLink: string;
}) {
	const router = useRouter();
	const [pending, setPending] = useState(false);
	const [error, setError] = useState<string | null>(null);

	async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault();
		setError(null);
		setPending(true);

		const form = new FormData(event.currentTarget);
		await authClient.signUp.email(
			{
				name: String(form.get("name")),
				email: String(form.get("email")),
				password: String(form.get("password")),
			},
			{
				onSuccess: () => {
					router.push("/");
					router.refresh();
				},
				onError: () => {
					setError(errorGeneric);
					setPending(false);
				},
			},
		);
	}

	return (
		<Card className="mx-auto w-full max-w-sm">
			<CardHeader>
				<CardTitle>{title}</CardTitle>
				<CardDescription>{description}</CardDescription>
			</CardHeader>
			<CardContent>
				<form className="flex flex-col gap-4" onSubmit={handleSubmit}>
					<div className="flex flex-col gap-1.5">
						<Label htmlFor="name">{nameLabel}</Label>
						<Input
							id="name"
							name="name"
							type="text"
							required
							autoComplete="name"
						/>
					</div>
					<div className="flex flex-col gap-1.5">
						<Label htmlFor="email">{emailLabel}</Label>
						<Input
							id="email"
							name="email"
							type="email"
							required
							autoComplete="email"
						/>
					</div>
					<div className="flex flex-col gap-1.5">
						<Label htmlFor="password">{passwordLabel}</Label>
						<Input
							id="password"
							name="password"
							type="password"
							required
							minLength={8}
							maxLength={128}
							autoComplete="new-password"
							aria-describedby="password-hint"
						/>
						<span id="password-hint" className="text-xs text-muted-foreground">
							{passwordHint}
						</span>
					</div>
					{error ? (
						<p role="alert" className="text-sm text-destructive">
							{error}
						</p>
					) : null}
					<Button type="submit" disabled={pending} className="w-full">
						{pending ? submitPendingLabel : submitLabel}
					</Button>
				</form>
				<p className="mt-4 text-sm text-muted-foreground">
					{signInPrompt} <Link href="/sign-in">{signInLink}</Link>
				</p>
			</CardContent>
		</Card>
	);
}
