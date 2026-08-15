<x-layouts.public :title="$promotion->title" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-3xl px-4 py-8 lg:px-8">
        <span class="text-xs font-semibold uppercase text-brand">{{ __('public.territory.promotions') }}</span>

        <h1 class="mt-2 text-3xl font-semibold text-ink">{{ $promotion->title }}</h1>

        <p class="mt-2 text-sm text-ink-muted">
            {{ __('public.promotions.valid', ['from' => $promotion->starts_at->toFormattedDateString(), 'until' => $promotion->ends_at->toFormattedDateString()]) }}
        </p>

        @if ($promotion->getFirstMediaUrl('image'))
            <img
                src="{{ $promotion->getFirstMediaUrl('image') }}"
                alt="{{ $promotion->title }}"
                class="mt-6 w-full rounded-lg object-cover"
            >
        @endif

        @if ($promotion->summary)
            <p class="mt-6 text-lg text-ink">{{ $promotion->summary }}</p>
        @endif

        @if ($promotion->body)
            <div class="mt-4 text-ink">
                {{ $promotion->body }}
            </div>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            <a
                href="{{ route('public.objects.show', ['lang' => app()->getLocale(), 'object' => $promotion->object]) }}"
                class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-ink hover:border-brand"
            >
                {{ $promotion->object->name }}
            </a>
            <a
                href="{{ route('public.territories.show', ['lang' => app()->getLocale(), 'territory' => $promotion->territory]) }}"
                class="rounded-full border border-gray-300 px-4 py-2 text-sm text-ink hover:border-brand hover:text-brand"
            >
                {{ $promotion->territory->name }}
            </a>
        </div>
    </div>
</x-layouts.public>
