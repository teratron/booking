{{--
    Home hero search. Visual language is the Figma source (Booking file,
    node 225:4066 "поле поиска"): a white, softly blurred bar split by
    divider lines, with a brand submit button. Fields are Destination and
    Type — the hero-search scope the object catalog actually defines —
    not the check-in/party-size fields Figma's literal
    hotel-booking template shows, which only apply where the optional
    room-reservation module is active for the viewed scope; offering them
    unconditionally would silently promise date-checked availability this
    portal cannot guarantee.

    Resolves its own data the same way the header does (self-contained,
    independent of whatever page includes it) so it can be dropped into
    any public template without threading data through that page's
    controller.
--}}
@php
    $shell = app(\App\Services\Shell\PublicShellDataProvider::class);
    $destinations = $shell->popularDestinations();
    $groups = $shell->navigationGroups();
    $catalogUrl = \Illuminate\Support\Facades\Route::has('public.catalog.index')
        ? route('public.catalog.index', ['lang' => app()->getLocale()])
        : url()->current();
@endphp
<form
    method="GET"
    action="{{ $catalogUrl }}"
    class="mx-auto flex w-full max-w-4xl flex-col divide-y divide-gray-200 rounded-lg bg-white text-left shadow-card sm:flex-row sm:divide-x sm:divide-y-0"
>
    <div class="flex-1 px-5 py-3">
        <label for="hero-search-territory" class="block text-xs text-placeholder">{{ __('public.catalog.filters.territory') }}</label>
        <select id="hero-search-territory" name="territoryId" class="mt-1 w-full border-none p-0 text-ink focus:outline-none focus:ring-0">
            <option value="">{{ __('public.catalog.filters.any') }}</option>
            @foreach ($destinations as $destination)
                <option value="{{ $destination->id }}">{{ $destination->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex-1 px-5 py-3">
        <label for="hero-search-type" class="block text-xs text-placeholder">{{ __('public.catalog.filters.type') }}</label>
        <select id="hero-search-type" name="type" class="mt-1 w-full border-none p-0 text-ink focus:outline-none focus:ring-0">
            <option value="">{{ __('public.catalog.filters.any') }}</option>
            @foreach ($groups as $group)
                @if (count($group->children) > 0)
                    <optgroup label="{{ $group->name }}">
                        @foreach ($group->children as $child)
                            <option value="{{ $child->id }}">{{ $child->name }}</option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endif
            @endforeach
        </select>
    </div>

    <div class="p-2 sm:flex sm:items-center">
        <button type="submit" class="w-full rounded-lg bg-brand px-8 py-3 text-base font-medium text-white hover:opacity-90 sm:w-auto">
            {{ __('public.home.hero.submit') }}
        </button>
    </div>
</form>
