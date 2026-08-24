{{--
    Shell header. Phone/tablet collapse navigation and (on phone) the
    switchers into the drawer; laptop/desktop render the full inline bar —
    the spec's own responsive matrix treats laptop and desktop identically,
    so both share the `lg:` breakpoint.
--}}
@php
    $shell = app(\App\Services\Shell\PublicShellDataProvider::class);
    $groups = $shell->navigationGroups();
    $languages = $shell->activeLanguages();
    $countries = $shell->activeCountries();
    $lang = app()->getLocale();
    $urlIfRouted = fn (string $name, array $params = []) => \Illuminate\Support\Facades\Route::has($name)
        ? route($name, ['lang' => $lang, ...$params])
        : null;
    $typeUrl = fn (int $id) => $urlIfRouted('public.catalog.index', ['type' => $id]);
    $ownerEntryHref = \Illuminate\Support\Facades\Route::has('filament.cabinet.auth.login')
        ? route('filament.cabinet.auth.login')
        : null;
    $portalName = app(\App\Services\Settings\SettingsRepository::class)->get('portal.name');
@endphp
<header class="bg-brand" x-data="{ mobileOpen: false }">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-4 lg:px-8">
        <x-public.nav-link :href="$urlIfRouted('public.home')" class="font-logo shrink-0 text-2xl text-white">
            {{ $portalName }}
        </x-public.nav-link>

        <div class="min-w-0 flex-1">
            <x-public.search-form />
        </div>

        <div class="hidden items-center gap-3 sm:flex">
            <x-public.country-switcher :countries="$countries" />
            <x-public.language-switcher :languages="$languages" />
        </div>

        <x-public.nav-link
            :href="$ownerEntryHref"
            class="hidden shrink-0 rounded border border-white px-3 py-1.5 text-sm font-medium text-white hover:bg-white hover:text-brand sm:inline-block"
        >
            {{ __('public.shell.header.owner_entry') }}
        </x-public.nav-link>

        <button
            type="button"
            class="shrink-0 rounded p-2 text-white lg:hidden"
            @click="mobileOpen = !mobileOpen"
            :aria-expanded="mobileOpen.toString()"
            aria-label="{{ __('public.shell.header.menu') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <x-public.nav :groups="$groups" class="hidden lg:block" />

    <div x-show="mobileOpen" x-cloak class="border-t border-white/30 px-4 py-4 lg:hidden">
        <div class="flex flex-col gap-3">
            @foreach ($groups as $group)
                <div>
                    <span class="block text-sm font-semibold uppercase tracking-wide text-white">{{ $group->name }}</span>
                    @if (count($group->children) > 0)
                        <div class="mt-1 flex flex-col gap-1 pl-3">
                            @foreach ($group->children as $child)
                                <x-public.nav-link :href="$typeUrl($child->id)" class="text-sm text-white">
                                    {{ $child->name }}
                                </x-public.nav-link>
                            @endforeach
                        </div>
                    @else
                        <x-public.nav-link :href="$typeUrl($group->id)" class="text-sm text-white">
                            {{ $group->name }}
                        </x-public.nav-link>
                    @endif
                </div>
            @endforeach

            <x-public.nav-link :href="$urlIfRouted('public.news.index')" class="text-sm font-medium text-white">{{ __('public.shell.nav.news') }}</x-public.nav-link>
            <x-public.nav-link :href="$urlIfRouted('public.promotions.index')" class="text-sm font-medium text-white">{{ __('public.shell.nav.promotions') }}</x-public.nav-link>
            <x-public.nav-link :href="$urlIfRouted('public.blog.index')" class="text-sm font-medium text-white">{{ __('public.shell.nav.blog') }}</x-public.nav-link>
            <x-public.nav-link :href="$urlIfRouted('public.about')" class="text-sm font-medium text-white">{{ __('public.shell.nav.about') }}</x-public.nav-link>
            <x-public.nav-link :href="$urlIfRouted('public.contacts')" class="text-sm font-medium text-white">{{ __('public.shell.nav.contacts') }}</x-public.nav-link>

            <div class="flex items-center gap-3 border-t border-white/30 pt-3 sm:hidden">
                <x-public.country-switcher :countries="$countries" />
                <x-public.language-switcher :languages="$languages" />
            </div>

            <x-public.nav-link :href="$ownerEntryHref" class="text-sm font-medium text-white underline sm:hidden">
                {{ __('public.shell.header.owner_entry') }}
            </x-public.nav-link>
        </div>
    </div>
</header>
