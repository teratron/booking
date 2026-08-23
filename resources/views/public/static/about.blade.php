<x-layouts.public :title="__('public.static.about.title')" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-3xl font-semibold text-ink">{{ __('public.static.about.title') }}</h1>

        <div class="mt-6 flex flex-col gap-4 text-ink">
            @foreach (__('public.static.about.body') as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </div>
</x-layouts.public>
