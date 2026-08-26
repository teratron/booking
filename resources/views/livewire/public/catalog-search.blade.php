<div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
    <h1 class="text-3xl font-medium text-ink">{{ __('public.catalog.title') }}</h1>

    <div class="mt-6 grid gap-8 lg:grid-cols-[280px_1fr]">
        <aside class="flex flex-col gap-6">
            <div>
                <label class="block text-base font-medium text-ink" for="catalog-territory">{{ __('public.catalog.filters.territory') }}</label>
                <select id="catalog-territory" wire:model.live="territoryId" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    <option value="">{{ __('public.catalog.filters.any') }}</option>
                    @foreach ($territories as $territory)
                        <option value="{{ $territory->id }}">{{ $territory->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-base font-medium text-ink" for="catalog-type">{{ __('public.catalog.filters.type') }}</label>
                <select id="catalog-type" wire:model.live="type" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                    <option value="">{{ __('public.catalog.filters.any') }}</option>
                    @foreach ($typeGroups as $group)
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

            <div>
                <p class="text-base font-medium text-ink">{{ __('public.catalog.filters.price') }}</p>
                <div class="mt-1 flex items-center gap-2">
                    <input
                        type="number"
                        wire:model.live.debounce.500ms="priceMin"
                        placeholder="{{ __('public.catalog.filters.price_min') }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    >
                    <span class="text-ink-muted">–</span>
                    <input
                        type="number"
                        wire:model.live.debounce.500ms="priceMax"
                        placeholder="{{ __('public.catalog.filters.price_max') }}"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    >
                </div>
            </div>

            <div>
                <label class="block text-base font-medium text-ink" for="catalog-rating">{{ __('public.catalog.filters.rating_min') }}</label>
                <input
                    id="catalog-rating"
                    type="number"
                    step="0.5"
                    min="0"
                    max="5"
                    wire:model.live.debounce.500ms="ratingMin"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                >
            </div>

            @if ($amenityGroups->isNotEmpty())
                <div>
                    <p class="text-base font-medium text-ink">{{ __('public.catalog.filters.amenities') }}</p>
                    <div class="mt-2 flex flex-col gap-3">
                        @foreach ($amenityGroups as $group)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ $group->name }}</p>
                                <div class="mt-1 flex flex-col gap-1">
                                    @foreach ($group->amenities as $amenity)
                                        <label class="flex items-center gap-2 text-sm text-ink">
                                            <input type="checkbox" wire:model.live="amenities" value="{{ $amenity->id }}" class="h-4 w-4 rounded-sm border-gray-400 accent-brand">
                                            {{ $amenity->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($selectedType && count($selectedType->attribute_schema ?? []) > 0)
                <div>
                    <p class="text-base font-medium text-ink">{{ __('public.catalog.filters.more') }}</p>
                    <div class="mt-2 flex flex-col gap-3">
                        @foreach ($selectedType->attribute_schema as $attribute)
                            @php
                                $key = $attribute['key'];
                                $label = $attribute['label'][app()->getLocale()] ?? $key;
                            @endphp
                            @if ($attribute['type'] === 'boolean')
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" wire:model.live="attrs.{{ $key }}" class="h-4 w-4 rounded-sm border-gray-400 accent-brand">
                                    {{ $label }}
                                </label>
                            @elseif ($attribute['type'] === 'number')
                                <div>
                                    <p class="text-xs text-ink-muted">{{ $label }}</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input type="number" wire:model.live.debounce.500ms="attrs.{{ $key }}.min" placeholder="{{ __('public.catalog.filters.price_min') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                                        <span class="text-ink-muted">–</span>
                                        <input type="number" wire:model.live.debounce.500ms="attrs.{{ $key }}.max" placeholder="{{ __('public.catalog.filters.price_max') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                                    </div>
                                </div>
                            @else
                                <div>
                                    <label class="text-xs text-ink-muted">{{ $label }}</label>
                                    <input type="text" wire:model.live.debounce.500ms="attrs.{{ $key }}" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink-muted">{{ trans_choice('public.catalog.results_count', $results->total()) }}</p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        wire:click="setViewMode('grid')"
                        @class(['rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-brand text-white' => $viewMode === 'grid', 'border border-gray-300 text-ink' => $viewMode !== 'grid'])
                    >
                        {{ __('public.catalog.view.grid') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setViewMode('list')"
                        @class(['rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-brand text-white' => $viewMode === 'list', 'border border-gray-300 text-ink' => $viewMode !== 'list'])
                    >
                        {{ __('public.catalog.view.list') }}
                    </button>
                </div>
            </div>

            <div class="mt-4" wire:ignore>
                <x-public.map :object-type-id="$type" />
            </div>

            @if ($results->isEmpty())
                <p class="mt-8 text-center text-ink-muted">{{ __('public.catalog.empty') }}</p>
            @else
                <div @class(['mt-6 grid gap-4' => true, 'sm:grid-cols-2' => $viewMode === 'grid', 'flex flex-col' => $viewMode === 'list'])>
                    @foreach ($results as $object)
                        <x-object-card :object="$object" :variant="$viewMode === 'grid' ? 'tile' : 'row'" wire:key="catalog-card-{{ $object->id }}" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $results->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
