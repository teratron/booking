{{--
    Rendered by the framework for any unresolved route (including an
    inactive or invented `{lang}` segment) — never indexable. Title and body
    read the administrator override first, falling back to the portal's own
    static copy when nobody has customized this status code yet.

    The illustration matches the Figma source (node 85:1278, "404") — a
    licensed stock illustration, cropped to the robot alone. The mockup's
    own copy ("Oops! 404 ERROR") is baked into that source image as
    un-translatable raster text, so it is cropped out here rather than
    reproduced; the page's own translatable eyebrow/title/body already
    carry the same message in whichever language is active.
--}}
@php $override = app(\App\Services\Seo\ErrorPageResolver::class)->resolve(404, app()->getLocale()); @endphp
<x-layouts.public :title="$override['title'] ?? __('public.legal.not_found.title')" :noindex="true">
    <div class="mx-auto flex max-w-4xl flex-col items-center gap-8 px-4 py-24 text-center sm:flex-row sm:text-left">
        <img
            src="{{ asset('images/errors/404-robot.png') }}"
            alt="{{ __('public.legal.not_found.illustration_alt') }}"
            class="w-full max-w-sm shrink-0 sm:w-1/2"
        >

        <div class="sm:w-1/2">
            <p class="text-lg font-medium text-ink-muted" aria-hidden="true">{{ __('public.legal.not_found.eyebrow') }}</p>
            <p class="text-8xl font-medium text-ink" aria-hidden="true">404</p>
            <h1 class="mt-4 text-2xl font-medium text-ink">{{ $override['title'] ?? __('public.legal.not_found.title') }}</h1>
            <p class="mt-4 text-ink-muted">{{ $override['body'] ?? __('public.legal.not_found.body') }}</p>

            <x-public.nav-link
                :href="\Illuminate\Support\Facades\Route::has('public.home') ? route('public.home', ['lang' => app()->getLocale()]) : url('/'.app()->getLocale())"
                class="mt-8 inline-block rounded-lg bg-brand px-6 py-3 text-base font-medium text-white hover:opacity-90"
            >
                {{ __('public.legal.not_found.home_link') }}
            </x-public.nav-link>
        </div>
    </div>
</x-layouts.public>
