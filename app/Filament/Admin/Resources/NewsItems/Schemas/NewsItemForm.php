<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsItems\Schemas;

use App\Filament\Support\SearchableModelSelect;
use App\Filament\Support\SeoMetadataFields;
use App\Models\ArticleCategory;
use App\Models\Language;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * `status`/`moderation_status`/`publish_at` are ordinary editable fields
 * here — the dedicated publish/schedule/pin/withdraw/archive header actions
 * on the edit page additionally journal the change, enforce the
 * future-date rule for a schedule, and (for publish) set both status
 * columns together, the same division `ArticleForm`/`EditArticle` already
 * draw.
 */
class NewsItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            SearchableModelSelect::users('author_id', __('panel.news_items.form.author'))
                ->default(fn (): ?int => Filament::auth()->user()?->getAuthIdentifier())
                ->required(),

            SearchableModelSelect::objects('object_id', __('panel.news_items.form.object'))
                ->placeholder(__('panel.news_items.form.any_object')),

            SearchableModelSelect::territories('territory_id', __('panel.news_items.form.territory')),

            Select::make('article_category_id')
                ->label(__('panel.news_items.form.category'))
                ->options(fn (): array => ArticleCategory::query()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (ArticleCategory $category): array => [$category->id => $category->name ?? $category->slug])
                    ->all())
                ->searchable(),

            Select::make('status')
                ->label(__('panel.news_items.form.status'))
                ->options([
                    'draft' => __('panel.news_items.status.draft'),
                    'scheduled' => __('panel.news_items.status.scheduled'),
                    'published' => __('panel.news_items.status.published'),
                    'withdrawn' => __('panel.news_items.status.withdrawn'),
                ])
                ->default('draft')
                ->required(),

            DateTimePicker::make('publish_at')
                ->label(__('panel.news_items.form.publish_at')),

            DateTimePicker::make('end_at')
                ->label(__('panel.news_items.form.end_at')),

            Toggle::make('is_pinned')
                ->label(__('panel.news_items.form.pinned'))
                ->default(false),

            SpatieMediaLibraryFileUpload::make('cover_image')
                ->label(__('panel.news_items.form.cover_image'))
                ->collection('cover_image')
                ->image(),

            SpatieMediaLibraryFileUpload::make('gallery')
                ->label(__('panel.news_items.form.gallery'))
                ->collection('gallery')
                ->image()
                ->multiple(),

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
                            ->label(__('panel.news_items.form.title'))
                            ->maxLength(255),
                        Textarea::make("translations.{$language->code}.summary")
                            ->label(__('panel.news_items.form.summary')),
                        Textarea::make("translations.{$language->code}.body")
                            ->label(__('panel.news_items.form.body'))
                            ->rows(10),
                        TextInput::make("translations.{$language->code}.slug")
                            ->label(__('panel.news_items.form.slug')),
                        ...SeoMetadataFields::for('panel.news_items.form', $language->code),
                    ]))
                ->all(),
        );
    }
}
