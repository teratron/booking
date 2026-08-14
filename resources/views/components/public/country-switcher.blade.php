{{--
    Lists every active country. Choosing one stores the browsing-country
    preference and navigates to that country's landing page once a later
    task registers it — a state change, so each option is its own POST,
    not a link.
--}}
@props(['countries'])
@php
    $current = session('public.country');
    $currentOption = collect($countries)->firstWhere('code', $current);
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-1 rounded border border-white px-2 py-1 text-sm font-medium text-white"
        :aria-expanded="open.toString()"
        aria-label="{{ __('public.shell.header.country') }}"
    >
        {{ $currentOption?->name ?? __('public.shell.header.country') }}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 10 6" aria-hidden="true">
            <path d="M0 0l5 6 5-6z" />
        </svg>
    </button>

    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 min-w-40 rounded bg-white py-1 shadow-lg">
        @foreach ($countries as $country)
            <form method="POST" action="{{ route('public.country-preference', ['lang' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="country" value="{{ $country->code }}">
                <button
                    type="submit"
                    class="block w-full px-3 py-1 text-left text-sm text-ink hover:bg-surface-muted {{ $country->code === $current ? 'font-semibold' : '' }}"
                >
                    {{ $country->name }}
                </button>
            </form>
        @endforeach
    </div>
</div>
