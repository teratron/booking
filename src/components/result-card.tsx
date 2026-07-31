import { StarIcon } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import type { CatalogResult } from "@/lib/discovery/catalog-query";

/**
 * Shared between Home's default list (T-4B01) and the Catalog page (T-4C01)
 * — l1-hotel-discovery.md §2 says both render "the same result-card
 * component". Originally slated as T-4C01's deliverable, built here first
 * since B01 was executed before C01 in this session's actual order; T-4C01
 * reuses this rather than redefining it.
 */
export function ResultCard({
	result,
	fromLabel,
	noReviewsLabel,
}: {
	result: CatalogResult;
	fromLabel: string;
	noReviewsLabel: string;
}) {
	return (
		<Card className="overflow-hidden">
			<Link href={`/hotel/${result.id}`} className="block">
				<div className="relative aspect-4/3 w-full bg-muted">
					{result.coverPhotoUrl ? (
						<Image
							src={result.coverPhotoUrl}
							alt={result.name}
							fill
							sizes="(min-width: 768px) 33vw, 100vw"
							className="object-cover"
						/>
					) : null}
				</div>
			</Link>
			<CardContent className="space-y-2">
				<div className="flex items-start justify-between gap-2">
					<Link
						href={`/hotel/${result.id}`}
						className="font-medium hover:underline"
					>
						{result.name}
					</Link>
					{result.starCategory ? (
						<span className="flex shrink-0 items-center gap-0.5 text-xs text-muted-foreground">
							{result.starCategory}
							<StarIcon className="size-3 fill-current" />
						</span>
					) : null}
				</div>
				<p className="text-sm text-muted-foreground">{result.address}</p>
				{result.amenityBadges.length > 0 ? (
					<div className="flex flex-wrap gap-1">
						{result.amenityBadges.map((badge) => (
							<Badge key={badge} variant="secondary">
								{badge}
							</Badge>
						))}
					</div>
				) : null}
				<div className="flex items-center justify-between pt-1">
					<span className="text-xs text-muted-foreground">
						{result.reviewCount > 0
							? `${result.avgRating?.toFixed(1)} (${result.reviewCount})`
							: noReviewsLabel}
					</span>
					{result.startingPrice !== null ? (
						<span className="text-sm font-medium">
							{fromLabel} {result.startingPrice} ₴
						</span>
					) : null}
				</div>
			</CardContent>
		</Card>
	);
}
