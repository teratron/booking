"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { authClient } from "@/lib/auth/client";

export function AuthNav({
	authenticated,
	userLabel,
	signInLabel,
	signOutLabel,
	signOutPendingLabel,
	myReservationsLabel,
}: {
	authenticated: boolean;
	userLabel: string | null;
	signInLabel: string;
	signOutLabel: string;
	signOutPendingLabel: string;
	myReservationsLabel: string;
}) {
	const router = useRouter();
	const [pending, setPending] = useState(false);

	if (!authenticated) {
		return (
			<Link href="/sign-in" className="text-sm font-medium">
				{signInLabel}
			</Link>
		);
	}

	return (
		<div className="flex items-center gap-2 text-sm">
			{userLabel ? (
				<span className="font-medium" data-testid="auth-nav-user">
					{userLabel}
				</span>
			) : null}
			<Link href="/account/reservations" className="font-medium">
				{myReservationsLabel}
			</Link>
			<Button
				variant="ghost"
				size="sm"
				disabled={pending}
				onClick={async () => {
					setPending(true);
					await authClient.signOut({
						fetchOptions: {
							onSuccess: () => {
								setPending(false);
								router.refresh();
							},
							onError: () => setPending(false),
						},
					});
				}}
			>
				{pending ? signOutPendingLabel : signOutLabel}
			</Button>
		</div>
	);
}
