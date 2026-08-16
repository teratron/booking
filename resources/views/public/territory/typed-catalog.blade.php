<x-layouts.public :metadata="$metadata" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <h1 class="text-3xl font-semibold text-ink">{{ $objectType->name }} — {{ $territory->name }}</h1>

        @if ($results->isEmpty())
            <p class="mt-6 text-ink-muted">{{ __('public.territory.typed_catalog_empty', ['territory' => $territory->name]) }}</p>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($results as $object)
                    <x-object-card :object="$object" wire:key="typed-catalog-card-{{ $object->id }}" />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $results->links() }}
            </div>
        @endif
    </div>
</x-layouts.public>
