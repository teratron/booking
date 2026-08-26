<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\Tables;

use App\Exceptions\BulkSelectionScopeException;
use App\Filament\Admin\Support\CountedBulkAction;
use App\Filament\Support\SearchableModelSelect;
use App\Jobs\ExecuteObjectBulkActionJob;
use App\Models\Country;
use App\Models\Language;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\PromotionLabel;
use App\Models\User;
use App\Services\Objects\ObjectBulkActionService;
use App\Services\Settings\SettingsRepository;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
            ->recordActions([EditAction::make()])
            ->groupedBulkActions(self::bulkActions())
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

    /** @return array<Filter|SelectFilter|TrashedFilter> */
    private static function filters(): array
    {
        return [
            // "Archived" here means what the resource's own soft-delete
            // scope means everywhere else — without this filter an archived
            // object is invisible in this list by design, not by accident.
            TrashedFilter::make(),

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

            SelectFilter::make('missing_translation')
                ->label(__('panel.objects.filters.missing_translation'))
                ->options(fn (): array => Language::query()->where('is_active', true)->orderBy('display_order')->pluck('short_label', 'code')->all())
                ->query(function (Builder $query, array $data): Builder {
                    $locale = $data['value'] ?? null;

                    if (! is_string($locale) || $locale === '') {
                        return $query;
                    }

                    return $query->whereDoesntHave(
                        'translations',
                        fn (Builder $translations) => $translations->where('locale', $locale)->where('needs_review', false),
                    );
                }),

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

            ...self::quickFilters(),
        ];
    }

    /**
     * One-click toggles, distinct from the dropdown filters above — the
     * five named quick filters an administrator reaches without opening a
     * menu first.
     *
     * @return list<Filter>
     */
    private static function quickFilters(): array
    {
        return [
            Filter::make('quick_available')
                ->label(__('panel.objects.filters.quick_available'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->where('availability_status', 'available')),

            Filter::make('quick_unspecified')
                ->label(__('panel.objects.filters.quick_unspecified'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->where('availability_status', 'unspecified')),

            Filter::make('quick_stale')
                ->label(__('panel.objects.filters.quick_stale'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->where(function (Builder $stale): void {
                    $cadenceDays = (int) app(SettingsRepository::class)->get('availability.confirmation_period_days');
                    $threshold = now()->subDays($cadenceDays);

                    $stale->whereNull('availability_last_confirmed_at')
                        ->orWhere('availability_last_confirmed_at', '<', $threshold);
                })),

            Filter::make('quick_active')
                ->label(__('panel.objects.filters.quick_active'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->where('status', 'published')),

            Filter::make('quick_expired_package')
                ->label(__('panel.objects.filters.quick_expired_package'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->whereHas(
                    'placement',
                    fn (Builder $placement) => $placement->where('ends_at', '<', now()->toDateString()),
                )),
        ];
    }

    /**
     * Ten bulk actions, grouped under one toolbar dropdown. Publish, hide,
     * and set-to-draft are three thin wrappers around the single
     * `change_status` operation the service exposes — the fixed `status`
     * parameter is the only thing distinguishing them, mirroring how the
     * single-object edit page collapses the same three transitions onto one
     * status-changing method.
     *
     * @return list<CountedBulkAction>
     */
    private static function bulkActions(): array
    {
        return [
            self::publishBulkAction(),
            self::hideBulkAction(),
            self::draftBulkAction(),
            self::archiveBulkAction(),
            self::assignPromotionLabelBulkAction(),
            self::moveTerritoryBulkAction(),
            self::assignManagerBulkAction(),
            self::notifyOwnersBulkAction(),
            self::exportBulkAction(),
            self::resetStaleAvailabilityBulkAction(),
        ];
    }

    /**
     * Resets every selected object's availability to `available` — the
     * bulk counterpart to the "status not updated recently" quick filter,
     * for the ordinary flow of filtering to the stale objects and clearing
     * the backlog in one confirmed action. Delegates to the same
     * `ObjectBulkActionService::execute()` scope check every other bulk
     * action here goes through, not a second, independent one.
     */
    private static function resetStaleAvailabilityBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('reset_stale_availability')
            ->label(__('panel.objects.bulk.reset_stale_availability'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.edit'))
            ->action(fn (Collection $records) => self::runBulkOperation($records, 'reset_stale_availability', []));
    }

    private static function publishBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('publish')
            ->label(__('panel.objects.bulk.publish'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.publish'))
            ->action(fn (Collection $records) => self::runBulkOperation(
                $records,
                'change_status',
                ['status' => 'published'],
            ));
    }

    private static function hideBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('hide')
            ->label(__('panel.objects.bulk.hide'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.edit'))
            ->action(fn (Collection $records) => self::runBulkOperation(
                $records,
                'change_status',
                ['status' => 'hidden'],
            ));
    }

    private static function draftBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('draft')
            ->label(__('panel.objects.bulk.draft'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.edit'))
            ->action(fn (Collection $records) => self::runBulkOperation(
                $records,
                'change_status',
                ['status' => 'draft'],
            ));
    }

    private static function archiveBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('archive')
            ->label(__('panel.objects.bulk.archive'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.delete'))
            ->action(fn (Collection $records) => self::runBulkOperation($records, 'archive', []));
    }

    private static function assignPromotionLabelBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('assign_promotion_label')
            ->label(__('panel.objects.bulk.assign_promotion_label'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.edit'))
            ->schema([
                Select::make('promotion_label_id')
                    ->label(__('panel.objects.bulk.promotion_label'))
                    ->options(fn (): array => PromotionLabel::query()
                        ->where('is_active', true)
                        ->with('translations')
                        ->get()
                        ->mapWithKeys(fn (PromotionLabel $label): array => [$label->id => $label->text ?? "#{$label->id}"])
                        ->all())
                    ->required(),
                DatePicker::make('starts_at')
                    ->label(__('panel.objects.bulk.starts_at'))
                    ->required(),
                DatePicker::make('ends_at')
                    ->label(__('panel.objects.bulk.ends_at'))
                    ->required(),
            ])
            ->action(fn (Collection $records, array $data) => self::runBulkOperation($records, 'assign_promotion_label', [
                'promotion_label_id' => (int) $data['promotion_label_id'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
            ]));
    }

    private static function moveTerritoryBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('move_territory')
            ->label(__('panel.objects.bulk.move_territory'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.edit'))
            ->schema([
                SearchableModelSelect::territories('territory_id', __('panel.objects.bulk.territory'))
                    ->required(),
            ])
            ->action(fn (Collection $records, array $data) => self::runBulkOperation($records, 'move_territory', [
                'territory_id' => (int) $data['territory_id'],
            ]));
    }

    private static function assignManagerBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('assign_manager')
            ->label(__('panel.objects.bulk.assign_manager'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.edit'))
            ->schema([
                SearchableModelSelect::users('manager_id', __('panel.objects.bulk.manager'))
                    ->required(),
            ])
            ->action(fn (Collection $records, array $data) => self::runBulkOperation($records, 'assign_manager', [
                'manager_id' => (int) $data['manager_id'],
            ]));
    }

    /**
     * Always queued: creating one notification row per selected object is
     * cheap, but the operation's cost belongs off the request thread on the
     * same grounds `export` does — a bulk administrative action a browser
     * tab should not be made to wait on.
     */
    private static function notifyOwnersBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('notify_owners')
            ->label(__('panel.objects.bulk.notify_owners'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.edit'))
            ->schema([
                TextInput::make('title')
                    ->label(__('panel.objects.bulk.notification_title'))
                    ->required(),
                Textarea::make('body')
                    ->label(__('panel.objects.bulk.notification_body'))
                    ->required(),
            ])
            ->action(fn (Collection $records, array $data) => self::runBulkOperation($records, 'notify_owners', [
                'title' => $data['title'],
                'body' => $data['body'],
            ], forceQueue: true));
    }

    /**
     * Always queued: a CSV write over the whole selection is exactly the
     * cost a request thread should not carry, regardless of how small the
     * selection happens to be.
     */
    private static function exportBulkAction(): CountedBulkAction
    {
        return CountedBulkAction::make('export')
            ->label(__('panel.objects.bulk.export'))
            ->authorize(fn (): bool => (bool) auth()->user()?->can('object.export'))
            ->action(fn (Collection $records) => self::runBulkOperation($records, 'export', [], forceQueue: true));
    }

    /**
     * Runs one bulk operation over the selected records — inline through
     * {@see ObjectBulkActionService::execute()} when the selection sits at or
     * under the configured threshold, or handed to the queued job above it.
     * A whole-selection scope failure is caught here rather than left to
     * surface as a request error: the administrator who triggered it needs
     * to see why nothing happened, not a generic failure page.
     *
     * @param  Collection<int, Object_>  $records
     * @param  array<string, mixed>  $parameters
     */
    private static function runBulkOperation(
        Collection $records,
        string $operation,
        array $parameters,
        bool $forceQueue = false,
    ): void {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        /** @var list<int> $objectIds */
        $objectIds = $records->modelKeys();

        $threshold = (int) app(SettingsRepository::class)->get('back_office.bulk_queue_threshold');

        if ($forceQueue || count($objectIds) > $threshold) {
            ExecuteObjectBulkActionJob::dispatch($operation, $objectIds, $parameters, $actor->id);

            Notification::make()
                ->title(__('panel.objects.bulk.queued'))
                ->success()
                ->send();

            return;
        }

        try {
            app(ObjectBulkActionService::class)->execute($operation, $objectIds, $parameters, $actor);
        } catch (BulkSelectionScopeException $exception) {
            Notification::make()
                ->danger()
                ->title(__('panel.objects.bulk.scope_denied'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->title(__('panel.objects.bulk.completed'))
            ->success()
            ->send();
    }
}
