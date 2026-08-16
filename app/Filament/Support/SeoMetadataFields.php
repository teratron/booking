<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * The seven per-language SEO fields every translatable public-facing entity
 * carries — shared across every resource form that edits one, so the field
 * set and its behaviour (URL validation, the three-state indexable control)
 * stay identical everywhere it appears rather than drifting resource by
 * resource.
 */
final class SeoMetadataFields
{
    /**
     * @return list<TextInput|Textarea|Select>
     */
    public static function for(string $labelNamespace, string $locale): array
    {
        return [
            TextInput::make("translations.{$locale}.seo_title")
                ->label(__("{$labelNamespace}.seo_title")),
            Textarea::make("translations.{$locale}.seo_description")
                ->label(__("{$labelNamespace}.seo_description")),
            TextInput::make("translations.{$locale}.seo_canonical_url")
                ->label(__("{$labelNamespace}.seo_canonical_url"))
                ->url(),
            // A plain toggle cannot distinguish "no explicit choice" (null —
            // the site policy decides) from an explicit "off"; a three-state
            // select can, and MetadataResolver relies on that distinction.
            Select::make("translations.{$locale}.seo_indexable")
                ->label(__("{$labelNamespace}.seo_indexable"))
                ->options([
                    '1' => __('panel.seo.indexable_yes'),
                    '0' => __('panel.seo.indexable_no'),
                ])
                ->placeholder(__('panel.seo.indexable_default')),
            TextInput::make("translations.{$locale}.seo_og_title")
                ->label(__("{$labelNamespace}.seo_og_title")),
            Textarea::make("translations.{$locale}.seo_og_description")
                ->label(__("{$labelNamespace}.seo_og_description")),
            TextInput::make("translations.{$locale}.seo_og_image")
                ->label(__("{$labelNamespace}.seo_og_image"))
                ->url(),
        ];
    }
}
