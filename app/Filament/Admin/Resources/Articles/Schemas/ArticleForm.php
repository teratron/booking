<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Articles\Schemas;

use App\Filament\Support\SearchableModelSelect;
use App\Filament\Support\SeoMetadataFields;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Language;
use App\Models\Object_;
use App\Models\Territory;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * `status`/`publish_at` are ordinary editable fields here, exactly like
 * `ObjectForm`'s own `status` select — the dedicated publish/schedule/
 * archive header actions on the edit page additionally journal the change
 * and enforce the future-date rule for a schedule; a plain field save
 * through this form does neither, the same division `ObjectForm` and
 * `EditObject`'s lifecycle actions already draw for objects.
 */
class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // The users table is unbounded (staff plus every registered
            // owner), so a full `->options()` hydrate here is the same
            // create-form pathology `SearchableModelSelect` exists to
            // prevent — server-side search instead.
            SearchableModelSelect::users('author_id', __('panel.articles.form.author'))
                ->default(fn (): ?int => Filament::auth()->user()?->getAuthIdentifier())
                ->required(),

            Select::make('article_category_id')
                ->label(__('panel.articles.form.category'))
                ->options(fn (): array => ArticleCategory::query()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (ArticleCategory $category): array => [$category->id => $category->name ?? $category->slug])
                    ->all())
                ->searchable(),

            Select::make('status')
                ->label(__('panel.articles.form.status'))
                ->options([
                    'draft' => __('panel.articles.status.draft'),
                    'scheduled' => __('panel.articles.status.scheduled'),
                    'published' => __('panel.articles.status.published'),
                ])
                ->default('draft')
                ->required(),

            DateTimePicker::make('publish_at')
                ->label(__('panel.articles.form.publish_at')),

            SpatieMediaLibraryFileUpload::make('cover_image')
                ->label(__('panel.articles.form.cover_image'))
                ->collection('cover_image')
                ->image(),

            Select::make('objects')
                ->relationship('objects', 'id')
                ->multiple()
                ->label(__('panel.articles.form.related_objects'))
                ->getOptionLabelFromRecordUsing(fn (Object_ $object): string => $object->name ?? "#{$object->id}")
                ->searchable(),

            Select::make('territories')
                ->relationship('territories', 'id')
                ->multiple()
                ->label(__('panel.articles.form.related_territories'))
                ->getOptionLabelFromRecordUsing(fn (Territory $territory): string => $territory->name ?? "#{$territory->id}")
                ->searchable(),

            Select::make('tags')
                ->relationship('tags', 'id')
                ->multiple()
                ->label(__('panel.articles.form.tags'))
                ->getOptionLabelFromRecordUsing(fn (ArticleTag $tag): string => $tag->name)
                ->searchable(),

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
                            ->label(__('panel.articles.form.title'))
                            ->maxLength(255),
                        Textarea::make("translations.{$language->code}.summary")
                            ->label(__('panel.articles.form.summary')),
                        Textarea::make("translations.{$language->code}.body")
                            ->label(__('panel.articles.form.body'))
                            ->rows(10),
                        TextInput::make("translations.{$language->code}.slug")
                            ->label(__('panel.articles.form.slug')),
                        ...SeoMetadataFields::for('panel.articles.form', $language->code),
                    ]))
                ->all(),
        );
    }
}
