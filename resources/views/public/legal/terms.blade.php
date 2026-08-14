<x-layouts.public :title="__('public.legal.terms.title')" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-3xl font-semibold text-ink">{{ __('public.legal.terms.title') }}</h1>

        <div class="mt-6 flex flex-col gap-4 text-ink">
            @foreach (__('public.legal.terms.body') as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </div>
</x-layouts.public>
