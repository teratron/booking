<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\Tables;

use App\Models\Country;
use App\Models\Object_;
use App\Models\ObjectType;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The object list, carrying every dimension the requirements name for it.
 *
 * Search covers name, phone, email, and identifier because those are the four
 * things a member of staff has in front of them when an owner calls: the
 * caller knows their own phone number, not the portal's primary key.
 *
 * Columns whose data arrives with the commerce phase — card caption, border
 * colour, pinned position, last bump — are absent rather than rendered empty.
 * An empty column reads as "this object has none", which is a different claim
 * from "the portal does not track this yet".
 */
class ObjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters(self::filters())
            ->defaultSort('updated_at', 'desc');
    }

    /** @return array<TextColumn> */
    private static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('panel.objects.columns.name'))
                ->getStateUsing(fn (Object_ $record): string => $record->name ?? "#{$record->id}")
                ->searchable(query: fn (Builder $query, string $search): Builder => $query
                    ->whereHas('translations', fn (Builder $translations) => $translations->where('name', 'ilike', "%{$search}%")))
                ->sortable(),

            TextColumn::make('objectType.key')
                ->label(__('panel.objects.columns.type'))
                ->badge()
                ->sortable(),

            TextColumn::make('country.code')
                ->label(__('panel.objects.columns.country'))
                ->sortable(),

            TextColumn::make('territory_id')
                ->label(__('panel.objects.columns.territory'))
                ->getStateUsing(function (Object_ $record): string {
                    $translation = $record->territory?->translations->first();

                    return (string) ($translation?->getAttribute('name') ?? '—');
                }),

            TextColumn::make('owner.name')
                ->label(__('panel.objects.columns.owner'))
                ->searchable()
                ->sortable(),

            TextColumn::make('status')
                ->label(__('panel.objects.columns.status'))
                ->badge()
                ->sortable(),

            TextColumn::make('moderation_status')
                ->label(__('panel.objects.columns.moderation_status'))
                ->badge()
                ->placeholder('—')
                ->sortable(),

            TextColumn::make('availability_status')
                ->label(__('panel.objects.columns.availability'))
                ->badge()
                ->sortable(),

            TextColumn::make('availability_last_confirmed_at')
                ->label(__('panel.objects.columns.availability_confirmed'))
                ->dateTime()
                ->placeholder('—')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('ulid')
                ->label(__('panel.objects.columns.identifier'))
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /** @return array<Filter|SelectFilter> */
    private static function filters(): array
    {
        return [
            SelectFilter::make('country_id')
                ->label(__('panel.objects.columns.country'))
                ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all()),

            SelectFilter::make('object_type_id')
                ->label(__('panel.objects.columns.type'))
                ->options(fn (): array => ObjectType::query()->pluck('key', 'id')->all()),

            SelectFilter::make('status')
                ->label(__('panel.objects.columns.status'))
                ->options([
                    'draft' => __('panel.objects.status.draft'),
                    'published' => __('panel.objects.status.published'),
                    'hidden' => __('panel.objects.status.hidden'),
                    'archived' => __('panel.objects.status.archived'),
                ]),

            SelectFilter::make('moderation_status')
                ->label(__('panel.objects.columns.moderation_status'))
                ->options([
                    'pending' => __('panel.objects.moderation.pending'),
                    'approved' => __('panel.objects.moderation.approved'),
                    'rejected' => __('panel.objects.moderation.rejected'),
                    'revision_requested' => __('panel.objects.moderation.revision_requested'),
                ]),

            SelectFilter::make('availability_status')
                ->label(__('panel.objects.columns.availability'))
                ->options([
                    'available' => __('panel.objects.availability.available'),
                    'unavailable' => __('panel.objects.availability.unavailable'),
                    'unspecified' => __('panel.objects.availability.unspecified'),
                ]),

            // Searching by the caller's own phone or address is the point of
            // this one: staff receive the details an owner can recite, not the
            // ones the portal filed them under.
            Filter::make('contact')
                ->schema([
                    TextInput::make('value')
                        ->label(__('panel.objects.filters.contact')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $value = $data['value'] ?? null;

                    if (! is_string($value) || $value === '') {
                        return $query;
                    }

                    return $query->whereHas(
                        'contactChannels',
                        fn (Builder $channels) => $channels->where('raw_value', 'ilike', "%{$value}%"),
                    );
                }),
        ];
    }
}
