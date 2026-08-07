<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Objects\Schemas;

use App\Models\Amenity;
use App\Models\Country;
use App\Models\Language;
use App\Models\ObjectType;
use App\Models\Territory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The object form's tab set, scoped to what this phase actually built.
 * `[TZ]` §104 names two further tabs deliberately absent here rather than
 * stubbed: rooms and prices belong to the back office's own separate
 * "Rooms & prices" section, which no task in this phase decomposes;
 * package/position, news, promotions, and statistics each depend on a
 * service a later phase builds. An absent tab states "not built yet"; a
 * present, empty one would state "this object has none" — a different and
 * false claim.
 *
 * Translated fields (name, descriptions, SEO) are held as nested form state
 * keyed by language code — `translations.{code}.name` — and reconciled
 * against the real `object_translations` rows explicitly in the resource
 * pages' fill/save hooks. `astrotomic/laravel-translatable` has no Filament
 * relationship sugar of its own to lean on here.
 */
class ObjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                self::coreInformationTab(),
                self::geographyTab(),
                self::typeAttributesTab(),
                self::seoTab(),
                self::contactsTab(),
                self::servicesTab(),
                self::ownerAndStaffTab(),
            ]),
        ]);
    }

    /**
     * One visually-grouped section per active language, rather than a nested
     * `Tabs` component — Filament 5's tab-switching state does not compose
     * cleanly when one `Tabs` component is nested inside another's schema,
     * and every active language needs to be visible and editable at once
     * regardless, not switched between one at a time.
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

    private static function coreInformationTab(): Tab
    {
        return Tab::make(__('panel.objects.form.tabs.core'))->schema([
            Select::make('object_type_id')
                ->label(__('panel.objects.columns.type'))
                ->options(fn (): array => ObjectType::query()->pluck('key', 'id')->all())
                ->required()
                ->live(),

            Select::make('status')
                ->label(__('panel.objects.columns.status'))
                ->options([
                    'draft' => __('panel.objects.status.draft'),
                    'published' => __('panel.objects.status.published'),
                    'hidden' => __('panel.objects.status.hidden'),
                    'archived' => __('panel.objects.status.archived'),
                ])
                ->default('draft')
                ->required(),

            ...self::perLanguageSections(fn (string $code): array => [
                TextInput::make("translations.{$code}.name")
                    ->label(__('panel.objects.form.name'))
                    ->maxLength(255),
                Textarea::make("translations.{$code}.short_description")
                    ->label(__('panel.objects.form.short_description')),
                Textarea::make("translations.{$code}.full_description")
                    ->label(__('panel.objects.form.full_description')),
            ]),
        ]);
    }

    /**
     * The fields a catalog view must render only if the object's type
     * declares them — an accommodation type exposing rooms and prices, a
     * dining type exposing cuisine and opening hours. Rendered from
     * `object_types.attribute_schema`, never a hard-coded field list, and
     * stored directly into the `objects.attributes`
     * JSONB column: the field names (`attributes.{key}`) already match that
     * column's own array-cast shape, so no reconciliation step is needed the
     * way translations require.
     */
    private static function typeAttributesTab(): Tab
    {
        return Tab::make(__('panel.objects.form.tabs.type_attributes'))
            ->schema(function (Get $get): array {
                $typeId = $get('object_type_id');

                if (! $typeId) {
                    return [];
                }

                $type = ObjectType::query()->find($typeId);

                if (! $type instanceof ObjectType) {
                    return [];
                }

                /** @var list<array{key: string, type: string, label?: array<string, string>}> $schema */
                $schema = $type->attribute_schema ?? [];
                $locale = app()->getLocale();

                return array_map(function (array $attribute) use ($locale): Component {
                    $label = $attribute['label'][$locale] ?? $attribute['key'];
                    $field = "attributes.{$attribute['key']}";

                    return match ($attribute['type']) {
                        'boolean' => Toggle::make($field)->label($label),
                        'number' => TextInput::make($field)->label($label)->numeric(),
                        default => TextInput::make($field)->label($label),
                    };
                }, $schema);
            });
    }

    private static function geographyTab(): Tab
    {
        return Tab::make(__('panel.objects.form.tabs.geography'))->schema([
            Select::make('country_id')
                ->label(__('panel.objects.columns.country'))
                ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all())
                ->required()
                ->live(),

            Select::make('territory_id')
                ->label(__('panel.objects.columns.territory'))
                ->options(function (Get $get): array {
                    $query = Territory::query()->with('translations');

                    if ($get('country_id')) {
                        $query->where('country_id', $get('country_id'));
                    }

                    return $query->get()
                        ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
                        ->all();
                })
                ->required(),

            TextInput::make('address')->label(__('panel.objects.form.address')),
            TextInput::make('latitude')->numeric(),
            TextInput::make('longitude')->numeric(),
        ]);
    }

    private static function seoTab(): Tab
    {
        return Tab::make(__('panel.objects.form.tabs.seo'))->schema(
            self::perLanguageSections(fn (string $code): array => [
                TextInput::make("translations.{$code}.slug")
                    ->label(__('panel.objects.form.seo_slug')),
                TextInput::make("translations.{$code}.seo_title")
                    ->label(__('panel.objects.form.seo_title')),
                Textarea::make("translations.{$code}.seo_description")
                    ->label(__('panel.objects.form.seo_description')),
            ]),
        );
    }

    private static function contactsTab(): Tab
    {
        return Tab::make(__('panel.objects.form.tabs.contacts'))->schema([
            Repeater::make('contactChannels')
                ->relationship()
                ->label(__('panel.objects.form.tabs.contacts'))
                ->schema([
                    TextInput::make('raw_value')
                        ->label(__('panel.objects.form.contact_value'))
                        ->required(),
                    TextInput::make('label')->label(__('panel.objects.form.contact_label')),
                ])
                ->defaultItems(0),
        ]);
    }

    private static function servicesTab(): Tab
    {
        return Tab::make(__('panel.objects.form.tabs.services'))->schema([
            Select::make('amenities')
                ->relationship('amenities', 'id')
                ->multiple()
                ->label(__('panel.objects.form.tabs.services'))
                ->getOptionLabelFromRecordUsing(fn (Amenity $amenity): string => $amenity->name ?? "#{$amenity->id}")
                ->options(fn (): array => Amenity::query()->with('translations')->get()
                    ->mapWithKeys(fn (Amenity $amenity): array => [$amenity->id => $amenity->name ?? "#{$amenity->id}"])
                    ->all()),
        ]);
    }

    private static function ownerAndStaffTab(): Tab
    {
        return Tab::make(__('panel.objects.form.tabs.owner_staff'))->schema([
            Select::make('owner_id')
                ->label(__('panel.objects.columns.owner'))
                ->relationship('owner', 'name')
                ->required()
                ->searchable(),
        ]);
    }
}
