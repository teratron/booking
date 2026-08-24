{{--
    Aggregate rating widget — a five-star strip (fractional fill via a
    clipped overlay) above the numeric average and review count, matching
    the Figma source (Booking file, node 225:3821 "рейтинг"). Shared by the
    catalog card and the object profile header, the two places an object's
    aggregate rating is shown. Renders nothing when there are no reviews
    yet — an untested 0/5 is not a rating.

    The star is the exact path traced from the Figma asset (node 225:3822
    "Group 24", star 37) rather than a redrawn approximation, so a viewer
    comparing against the source sees the same glyph.
--}}
@props(['average', 'count'])
@if ($count > 0)
    <div class="shrink-0 text-right">
        <div class="relative inline-flex" aria-hidden="true">
            <div class="flex gap-0.5 text-gray-300">
                @for ($i = 0; $i < 5; $i++)
                    <svg viewBox="0 0 24 21" class="h-4 w-4 fill-current"><path d="M12.1427 0L14.8689 7.81711H23.691L16.5538 12.6484L19.28 20.4655L12.1427 15.6342L5.00539 20.4655L7.73159 12.6484L0.594305 7.81711H9.41648L12.1427 0Z" /></svg>
                @endfor
            </div>
            <div class="absolute inset-0 flex overflow-hidden" style="width: {{ min(100, max(0, (float) $average / 5 * 100)) }}%">
                <div class="flex gap-0.5 text-brand">
                    @for ($i = 0; $i < 5; $i++)
                        <svg viewBox="0 0 24 21" class="h-4 w-4 fill-current"><path d="M12.1427 0L14.8689 7.81711H23.691L16.5538 12.6484L19.28 20.4655L12.1427 15.6342L5.00539 20.4655L7.73159 12.6484L0.594305 7.81711H9.41648L12.1427 0Z" /></svg>
                    @endfor
                </div>
            </div>
        </div>
        <div class="mt-1 text-2xl font-medium text-ink sm:text-3xl">{{ number_format((float) $average, 1) }} / 5</div>
        <div class="text-xs text-ink-muted sm:text-sm">{{ trans_choice('public.catalog.card.reviews', $count) }}</div>
    </div>
@endif
