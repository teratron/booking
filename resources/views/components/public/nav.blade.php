{{--
    Data-driven primary navigation: a top-level object type with children
    renders as a dropdown group, one without renders as a direct link.
    Adding a type in the back office adds an entry here without a code
    change.
--}}
@props(['groups'])
@php
    $lang = app()->getLocale();
    $urlIfRouted = fn (string $name, array $params = []) => \Illuminate\Support\Facades\Route::has($name)
        ? route($name, ['lang' => $lang, ...$params])
        : null;
    $typeUrl = fn (string $key) => $urlIfRouted('public.catalog.index', ['type' => $key]);
@endphp
<nav {{ $attributes->merge(['class' => 'border-t border-white/20 bg-brand']) }} aria-label="{{ __('public.shell.nav.primary') }}">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-6 px-4 py-2 lg:px-8">
        @foreach ($groups as $group)
            @if (count($group->children) > 0)
                <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                    <button type="button" class="flex items-center gap-1 text-sm font-medium text-white" @click="open = !open" :aria-expanded="open.toString()">
                        {{ $group->name }}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 10 6" aria-hidden="true">
                            <path d="M0 0l5 6 5-6z" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak class="absolute left-0 z-20 mt-2 min-w-40 rounded bg-white py-1 shadow-lg">
                        @foreach ($group->children as $child)
                            <x-public.nav-link :href="$typeUrl($child->key)" class="block px-3 py-1 text-sm text-ink hover:bg-surface-muted">
                                {{ $child->name }}
                            </x-public.nav-link>
                        @endforeach
                    </div>
                </div>
            @else
                <x-public.nav-link :href="$typeUrl($group->key)" class="text-sm font-medium text-white">
                    {{ $group->name }}
                </x-public.nav-link>
            @endif
        @endforeach

        <x-public.nav-link :href="$urlIfRouted('public.news.index')" class="text-sm font-medium text-white">
            {{ __('public.shell.nav.news') }}
        </x-public.nav-link>
        <x-public.nav-link :href="$urlIfRouted('public.promotions.index')" class="text-sm font-medium text-white">
            {{ __('public.shell.nav.promotions') }}
        </x-public.nav-link>
        <x-public.nav-link :href="$urlIfRouted('public.blog.index')" class="text-sm font-medium text-white">
            {{ __('public.shell.nav.blog') }}
        </x-public.nav-link>
        <x-public.nav-link :href="$urlIfRouted('public.about')" class="text-sm font-medium text-white">
            {{ __('public.shell.nav.about') }}
        </x-public.nav-link>
        <x-public.nav-link :href="$urlIfRouted('public.contacts')" class="text-sm font-medium text-white">
            {{ __('public.shell.nav.contacts') }}
        </x-public.nav-link>
    </div>
</nav>
