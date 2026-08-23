<x-layouts.public :title="__('public.static.contacts.title')" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-3xl font-semibold text-ink">{{ __('public.static.contacts.title') }}</h1>

        <div class="mt-6 flex flex-col gap-4 text-ink">
            @foreach (__('public.static.contacts.body') as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        @if ($contactEmail !== '' || $contactPhone !== '')
            <dl class="mt-8 flex flex-col gap-3 border-t border-gray-200 pt-6">
                @if ($contactPhone !== '')
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">{{ __('public.static.contacts.phone_label') }}</dt>
                        <dd class="text-ink"><a href="tel:{{ $contactPhone }}" class="hover:underline">{{ $contactPhone }}</a></dd>
                    </div>
                @endif
                @if ($contactEmail !== '')
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">{{ __('public.static.contacts.email_label') }}</dt>
                        <dd class="text-ink"><a href="mailto:{{ $contactEmail }}" class="hover:underline">{{ $contactEmail }}</a></dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>
</x-layouts.public>
