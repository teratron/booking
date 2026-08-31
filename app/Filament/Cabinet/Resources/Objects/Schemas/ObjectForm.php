<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Objects\Schemas;

use App\Filament\Support\SeoMetadataFields;
use App\Jobs\StalenessSweepJob;
use App\Models\ContactChannelType;
use App\Models\Country;
use App\Models\Language;
use App\Models\ModerationRequest;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Services\Cabinet\ObjectEditService;
use App\Services\Cabinet\ObjectPublicationEligibilityService;
use App\Services\Cabinet\ObjectStalenessService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's own object-edit form — grouped into the five sections
 * every later object-management screen in this track mirrors: Core,
 * Geography, Contacts, Translations, SEO. Availability is deliberately
 * absent; it is a one-tap toggle with its own dedicated write path, never a
 * field on this form (routing it through an ordinary save would skip the
 * paired history row a dedicated service keeps atomic with the status
 * write).
 *
 * Translated fields (name, descriptions, SEO) are collected as nested form
 * state keyed by language code — `translations.{code}.*` — and reconciled
 * against the real `object_translations` rows explicitly on the edit page,
 * exactly as the admin object form's own mechanism does; astrotomic's
 * translation package has no Filament relationship binding of its own to
 * lean on here.
 *
 * Contact channels use Filament's own `Repeater::relationship()` binding —
 * the one section this form does not gate behind moderation. That binding
 * persists as part of the schema's own save lifecycle, before the edit
 * page's `handleRecordUpdate()` ever runs, so there is no hook by which a
 * pending-review decision could hold a contact-channel write back; contact
 * details are also the mechanism this portal's whole conversion path
 * depends on; delaying a corrected phone number behind a review queue would
 * work against the portal's own purpose.
 */
class ObjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            self::moderationFeedbackSection(),
            self::eligibilitySection(),
            self::stalenessSection(),
            self::coreSection(),
            self::geographySection(),
            self::contactsSection(),
            self::translationsSection(),
            self::seoSection(),
        ]);
    }

    /**
     * Surfaces the reason behind the most recent decision an owner still
     * needs to act on. Absent entirely once nothing is pending, rejected, or
     * sent back for revision — an approved history has nothing left to say.
     */
    private static function moderationFeedbackSection(): Section
    {
        return Section::make(__('panel.cabinet.objects.moderation_feedback.title'))
            ->schema([
                Placeholder::make('moderation_feedback_status')
                    ->hiddenLabel()
                    ->content(function (?Object_ $record): string {
                        $request = self::latestActionableRequest($record);

                        if (! $request instanceof ModerationRequest) {
                            return '';
                        }

                        return match ($request->decision) {
                            'pending' => __('panel.cabinet.objects.moderation_feedback.pending'),
                            'rejected' => __('panel.cabinet.objects.moderation_feedback.rejected_with_reason', [
                                'reason' => $request->rejection_reason ?? '',
                            ]),
                            'revision_requested' => __('panel.cabinet.objects.moderation_feedback.revision_requested_with_reason', [
                                'reason' => $request->comment ?? '',
                            ]),
                            default => '',
                        };
                    }),
            ])
            ->visible(fn (?Object_ $record): bool => self::latestActionableRequest($record) instanceof ModerationRequest);
    }

    private static function latestActionableRequest(?Object_ $record): ?ModerationRequest
    {
        if (! $record instanceof Object_ || ! $record->exists) {
            return null;
        }

        return app(ObjectEditService::class)->latestActionableRequest($record);
    }

    /**
     * A plain, always-editable readiness notice — not a blocking validation
     * error, since a draft is explicitly allowed to stay incomplete. Absent
     * once the object is already published (an already-live object's own
     * completeness is no longer in question) or once nothing is missing.
     */
    private static function eligibilitySection(): Section
    {
        return Section::make(__('panel.cabinet.objects.eligibility.title'))
            ->schema([
                Placeholder::make('eligibility_missing')
                    ->hiddenLabel()
                    ->content(function (?Object_ $record): string {
                        if (! $record instanceof Object_) {
                            return '';
                        }

                        $missing = app(ObjectPublicationEligibilityService::class)->missingRequirements($record);

                        return collect($missing)
                            ->map(fn (string $key): string => __("panel.cabinet.objects.eligibility.{$key}"))
                            ->implode(' ');
                    }),
            ])
            ->visible(function (?Object_ $record): bool {
                if (! $record instanceof Object_ || $record->status === 'published') {
                    return false;
                }

                return ! app(ObjectPublicationEligibilityService::class)->isEligibleForPublication($record);
            });
    }

    /**
     * Surfaces the same staleness flag {@see StalenessSweepJob}
     * already raised as an inbox notification — advisory only, so this
     * section never disables the form or any lifecycle action underneath
     * it, only informs.
     */
    private static function stalenessSection(): Section
    {
        return Section::make(__('panel.cabinet.objects.staleness.title'))
            ->schema([
                Placeholder::make('staleness_notice')
                    ->hiddenLabel()
                    ->content(__('panel.cabinet.objects.staleness.notice')),
            ])
            ->visible(fn (?Object_ $record): bool => $record instanceof Object_
                && $record->exists
                && app(ObjectStalenessService::class)->isFlagged($record));
    }

    /**
     * The object's declared type (read-only — assigned once by staff at
     * provisioning, never owner-editable, since it decides which of the
     * fields below even exist) plus the field set that type's own
     * `attribute_schema` declares, rendered exactly as the admin object
     * form's identical mechanism does.
     */
    private static function coreSection(): Section
    {
        return Section::make(__('panel.cabinet.objects.sections.core'))
            ->columns(2)
            ->schema(function (?Object_ $record): array {
                $type = $record?->objectType;

                $components = [
                    Placeholder::make('object_type_display')
                        ->label(__('panel.objects.columns.type'))
                        ->content(fn (): string => $type instanceof ObjectType ? ($type->name ?? '—') : '—'),
                ];

                if (! $type instanceof ObjectType) {
                    return $components;
                }

                /** @var list<array{key: string, type: string, label?: array<string, string>}> $schema */
                $schema = $type->attribute_schema ?? [];
                $locale = app()->getLocale();

                foreach ($schema as $attribute) {
                    $label = $attribute['label'][$locale] ?? $attribute['key'];
                    $field = "attributes.{$attribute['key']}";

                    $components[] = match ($attribute['type']) {
                        'boolean' => Toggle::make($field)->label($label),
                        'number' => TextInput::make($field)->label($label)->numeric(),
                        default => TextInput::make($field)->label($label),
                    };
                }

                return $components;
            });
    }

    /**
     * Coordinates are required here — unlike the admin form, which trusts
     * staff judgement on when a location is precise enough — because the
     * catalog's own map view has nothing to plot without them.
     */
    private static function geographySection(): Section
    {
        return Section::make(__('panel.cabinet.objects.sections.geography'))
            ->columns(2)
            ->schema([
                Select::make('country_id')
                    ->label(__('panel.objects.columns.country'))
                    ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all())
                    ->required()
                    ->live(),

                // Server-side search, scoped to the selected country: the
                // territory registry is unbounded and stays runtime-
                // extensible, so hydrating it in full on every form render
                // is the same create-form pathology the admin object form
                // and `SearchableModelSelect` already avoid — here with the
                // country filter that helper's generic `territories()` does
                // not carry.
                Select::make('territory_id')
                    ->label(__('panel.objects.columns.territory'))
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search, Get $get): array {
                        return Territory::query()
                            ->with('translations')
                            ->where('is_active', true)
                            ->when($get('country_id'), fn (Builder $query, $countryId) => $query->where('country_id', $countryId))
                            ->whereHas('translations', fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%"))
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
                            ->all();
                    })
                    // Never null for a real id — see the admin object form's
                    // own note: a null label makes Filament reject the value.
                    ->getOptionLabelUsing(function (int|string|null $value): ?string {
                        $territory = Territory::query()->with('translations')->find($value);

                        return $territory === null ? null : ($territory->name ?? "#{$territory->id}");
                    })
                    ->required(),

                TextInput::make('address')
                    ->label(__('panel.objects.form.address'))
                    ->columnSpanFull(),

                TextInput::make('latitude')
                    ->label(__('panel.cabinet.objects.form.latitude'))
                    ->numeric()
                    ->required(),

                TextInput::make('longitude')
                    ->label(__('panel.cabinet.objects.form.longitude'))
                    ->numeric()
                    ->required(),
            ]);
    }

    private static function contactsSection(): Section
    {
        return Section::make(__('panel.cabinet.objects.sections.contacts'))
            ->schema([
                Repeater::make('contactChannels')
                    ->relationship()
                    ->hiddenLabel()
                    ->schema([
                        Select::make('contact_channel_type_id')
                            ->label(__('panel.objects.form.contact_type'))
                            ->relationship(
                                name: 'contactChannelType',
                                titleAttribute: 'key',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->with('translations'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (ContactChannelType $type): string => $type->display_name ?? $type->key)
                            ->required(),
                        TextInput::make('raw_value')
                            ->label(__('panel.objects.form.contact_value'))
                            ->required(),
                        TextInput::make('label')
                            ->label(__('panel.objects.form.contact_label')),
                    ])
                    ->defaultItems(0),
            ]);
    }

    private static function translationsSection(): Section
    {
        return Section::make(__('panel.cabinet.objects.sections.translations'))
            ->schema(self::perLanguageSections(fn (string $code): array => [
                TextInput::make("translations.{$code}.name")
                    ->label(__('panel.objects.form.name'))
                    ->maxLength(255),
                Textarea::make("translations.{$code}.short_description")
                    ->label(__('panel.objects.form.short_description')),
                Textarea::make("translations.{$code}.full_description")
                    ->label(__('panel.objects.form.full_description')),
            ]));
    }

    private static function seoSection(): Section
    {
        return Section::make(__('panel.cabinet.objects.sections.seo'))
            ->schema(self::perLanguageSections(fn (string $code): array => [
                TextInput::make("translations.{$code}.slug")
                    ->label(__('panel.objects.form.seo_slug')),
                ...SeoMetadataFields::for('panel.objects.form', $code),
            ]));
    }

    /**
     * One visually-grouped, collapsible section per active language, rather
     * than a nested `Tabs` component — every active language needs to stay
     * visible and editable at once here, not switched between one at a time,
     * matching the admin object form's own identical mechanism and reasoning.
     *
     * @return list<Section>
     */
    private static function perLanguageSections(callable $fields): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn (Language $language): Section => Section::make($language->short_label)
                    ->schema($fields($language->code))
                    ->collapsible())
                ->all(),
        );
    }
}
