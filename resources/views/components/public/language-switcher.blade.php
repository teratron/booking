{{--
    Lists every active language. Each option navigates to the same route
    and parameters the visitor is already on, only the `lang` segment
    swapped, so switching language preserves position.
--}}
@props(['languages'])
@php
    $resolver = app(\App\Services\Shell\LocaleSwitchResolver::class);
    $current = app()->getLocale();
    $currentLabel = collect($languages)->firstWhere('code', $current)?->shortLabel ?? strtoupper($current);
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-1 rounded border border-white px-2 py-1 text-sm font-medium text-white"
        :aria-expanded="open.toString()"
        aria-label="{{ __('public.shell.header.language') }}"
    >
        {{ $currentLabel }}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 10 6" aria-hidden="true">
            <path d="M0 0l5 6 5-6z" />
        </svg>
    </button>

    <div x-show="open" x-cloak @click="open = false" class="absolute right-0 z-20 mt-1 min-w-24 rounded bg-white py-1 shadow-lg">
        @foreach ($languages as $language)
            <a
                href="{{ $resolver->targetUrl(request(), $language->code) }}"
                class="block px-3 py-1 text-sm text-ink hover:bg-surface-muted {{ $language->code === $current ? 'font-semibold' : '' }}"
            >
                {{ $language->shortLabel }}
            </a>
        @endforeach
    </div>
</div>
