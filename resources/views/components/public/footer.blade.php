{{--
    Shell footer. The Figma source (home page frame, node 225:4047) shows
    brand/about, contacts, and social links only — popular destinations,
    object categories, and the legal links are added here per the
    specification's own composition, which the mockup does not fully depict
    (Figma is a visual concept, not the scope authority).
--}}
@php
    $shell = app(\App\Services\Shell\PublicShellDataProvider::class);
    $settings = app(\App\Services\Settings\SettingsRepository::class);
    $lang = app()->getLocale();
    $urlIfRouted = fn (string $name, array $params = []) => \Illuminate\Support\Facades\Route::has($name)
        ? route($name, ['lang' => $lang, ...$params])
        : null;
    $groups = $shell->navigationGroups();
    $countries = $shell->activeCountries();
    $portalName = $settings->get('portal.name');
    $contactEmail = $settings->get('portal.contact_email');
    $contactPhone = $settings->get('portal.contact_phone');
    $socialLinks = array_filter([
        'instagram' => $settings->get('portal.social_instagram_url'),
        'telegram' => $settings->get('portal.social_telegram_url'),
        'facebook' => $settings->get('portal.social_facebook_url'),
    ]);
    $addObjectHref = \Illuminate\Support\Facades\Route::has('filament.cabinet.auth.login')
        ? route('filament.cabinet.auth.login')
        : null;
@endphp
<footer class="bg-brand text-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-5 lg:px-8">
        <div class="lg:col-span-1">
            <x-public.nav-link :href="$urlIfRouted('public.home')" class="text-3xl font-semibold italic">
                {{ $portalName }}
            </x-public.nav-link>
        </div>

        <div>
            <h3 class="text-lg font-semibold">{{ __('public.shell.footer.about_heading') }}</h3>
            <ul class="mt-2 flex flex-col gap-1 text-sm text-white/90">
                <li><x-public.nav-link :href="$urlIfRouted('public.about')">{{ __('public.shell.nav.about') }}</x-public.nav-link></li>
                <li><x-public.nav-link :href="$urlIfRouted('public.blog.index')">{{ __('public.shell.nav.blog') }}</x-public.nav-link></li>
            </ul>
        </div>

        <div>
            <h3 class="text-lg font-semibold">{{ __('public.shell.footer.contacts_heading') }}</h3>
            <ul class="mt-2 flex flex-col gap-1 text-sm text-white/90">
                @if ($contactPhone !== '')
                    <li>{{ $contactPhone }}</li>
                @endif
                @if ($contactEmail !== '')
                    <li>{{ $contactEmail }}</li>
                @endif
                <li><x-public.nav-link :href="$urlIfRouted('public.contacts')">{{ __('public.shell.nav.contacts') }}</x-public.nav-link></li>
            </ul>

            @if (count($socialLinks) > 0)
                <div class="mt-3 flex items-center gap-2">
                    @foreach ($socialLinks as $network => $href)
                        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="block h-7 w-7 overflow-hidden rounded">
                            <img src="{{ asset("images/social/{$network}.png") }}" alt="{{ $network }}" class="h-full w-full object-cover">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h3 class="text-lg font-semibold">{{ __('public.shell.footer.destinations_heading') }}</h3>
            <ul class="mt-2 flex flex-col gap-1 text-sm text-white/90">
                @foreach ($countries as $country)
                    <li>
                        <x-public.nav-link :href="$urlIfRouted('public.territories.show', ['country' => $country->code])">
                            {{ $country->name }}
                        </x-public.nav-link>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="text-lg font-semibold">{{ __('public.shell.footer.categories_heading') }}</h3>
            <ul class="mt-2 flex flex-col gap-1 text-sm text-white/90">
                @foreach ($groups as $group)
                    <li>
                        <x-public.nav-link :href="$urlIfRouted('public.catalog.index', ['type' => $group->key])">
                            {{ $group->name }}
                        </x-public.nav-link>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="border-t border-white/30">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 lg:px-8">
            <div class="flex flex-wrap gap-4 text-sm">
                <x-public.nav-link :href="$urlIfRouted('public.legal.privacy')">{{ __('public.shell.footer.privacy') }}</x-public.nav-link>
                <x-public.nav-link :href="$urlIfRouted('public.legal.terms')">{{ __('public.shell.footer.terms') }}</x-public.nav-link>
            </div>

            <x-public.nav-link
                :href="$addObjectHref"
                class="rounded-lg bg-white px-6 py-3 text-sm font-semibold text-brand hover:opacity-90"
            >
                {{ __('public.shell.footer.add_object') }}
            </x-public.nav-link>
        </div>
    </div>
</footer>
