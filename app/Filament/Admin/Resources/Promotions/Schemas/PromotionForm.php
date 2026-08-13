<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Promotions\Schemas;

use App\Models\Language;
use App\Models\Object_;
use App\Models\Territory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * `status`/`moderation_status`/`starts_at` are ordinary editable fields
 * here — the dedicated publish/schedule/archive header actions on the edit
 * page additionally journal the change and (for publish) set both status
 * columns together, mirroring `NewsItemForm`/`EditNewsItem`.
 */
class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('object_id')
                ->label(__('panel.promotions.form.object'))
                ->options(fn (): array => Object_::query()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (Object_ $object): array => [$object->id => $object->name ?? "#{$object->id}"])
                    ->all())
                ->required()
                ->searchable(),

            Select::make('territory_id')
                ->label(__('panel.promotions.form.territory'))
                ->options(fn (): array => Territory::query()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (Territory $territory): array => [$territory->id => $territory->name ?? "#{$territory->id}"])
                    ->all())
                ->required()
                ->searchable(),

            Select::make('status')
                ->label(__('panel.promotions.form.status'))
                ->options([
                    'draft' => __('panel.promotions.status.draft'),
                    'scheduled' => __('panel.promotions.status.scheduled'),
                    'published' => __('panel.promotions.status.published'),
                    'archived' => __('panel.promotions.status.archived'),
                ])
                ->default('draft')
                ->required(),

            DatePicker::make('starts_at')
                ->label(__('panel.promotions.form.starts_at'))
                ->default(now())
                ->required(),

            DatePicker::make('ends_at')
                ->label(__('panel.promotions.form.ends_at'))
                ->required(),

            SpatieMediaLibraryFileUpload::make('image')
                ->label(__('panel.promotions.form.image'))
                ->collection('image')
                ->image(),

            ...self::perLanguageSections(),
        ]);
    }

    /** @return list<Section> */
    private static function perLanguageSections(): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn (Language $language): Section => Section::make($language->short_label)
                    ->collapsible()
                    ->schema([
                        TextInput::make("translations.{$language->code}.title")
                            ->label(__('panel.promotions.form.title'))
                            ->maxLength(255),
                        Textarea::make("translations.{$language->code}.summary")
                            ->label(__('panel.promotions.form.summary')),
                        TextInput::make("translations.{$language->code}.slug")
                            ->label(__('panel.promotions.form.slug')),
                        TextInput::make("translations.{$language->code}.seo_title")
                            ->label(__('panel.promotions.form.seo_title')),
                        Textarea::make("translations.{$language->code}.seo_description")
                            ->label(__('panel.promotions.form.seo_description')),
                    ]))
                ->all(),
        );
    }
}
