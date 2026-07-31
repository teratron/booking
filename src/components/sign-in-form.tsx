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

export function SignInForm({
	title,
	description,
	emailLabel,
	passwordLabel,
	submitLabel,
	submitPendingLabel,
	errorGeneric,
	signUpPrompt,
	signUpLink,
}: {
	title: string;
	description: string;
	emailLabel: string;
	passwordLabel: string;
	submitLabel: string;
	submitPendingLabel: string;
	errorGeneric: string;
	signUpPrompt: string;
	signUpLink: string;
}) {
	const router = useRouter();
	const [pending, setPending] = useState(false);
	const [error, setError] = useState<string | null>(null);

	async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault();
		setError(null);
		setPending(true);

		const form = new FormData(event.currentTarget);
		await authClient.signIn.email(
			{
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
							autoComplete="current-password"
						/>
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
					{signUpPrompt} <Link href="/sign-up">{signUpLink}</Link>
				</p>
			</CardContent>
		</Card>
	);
}
