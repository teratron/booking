"use client";

import { MinusIcon, PlusIcon } from "lucide-react";
import { Button } from "@/components/ui/button";

/**
 * Plain controlled value/onChange — no internal-only state — so Phase 6's
 * room-detail popup can reuse it unmodified (l1-room-reservation.md §6).
 */
export function GuestCountPicker({
	value,
	onChange,
	label,
	decreaseLabel,
	increaseLabel,
	min = 1,
	max = 20,
}: {
	value: number;
	onChange: (next: number) => void;
	label: string;
	decreaseLabel: string;
	increaseLabel: string;
	min?: number;
	max?: number;
}) {
	function clamp(next: number) {
		return Math.min(max, Math.max(min, next));
	}

	return (
		<div className="flex items-center gap-2">
			<span className="text-sm text-muted-foreground">{label}</span>
			<div className="flex items-center gap-1">
				<Button
					type="button"
					variant="outline"
					size="icon-sm"
					aria-label={decreaseLabel}
					disabled={value <= min}
					onClick={() => onChange(clamp(value - 1))}
				>
					<MinusIcon />
				</Button>
				<span className="w-6 text-center text-sm tabular-nums">{value}</span>
				<Button
					type="button"
					variant="outline"
					size="icon-sm"
					aria-label={increaseLabel}
					disabled={value >= max}
					onClick={() => onChange(clamp(value + 1))}
				>
					<PlusIcon />
				</Button>
			</div>
		</div>
	);
}
