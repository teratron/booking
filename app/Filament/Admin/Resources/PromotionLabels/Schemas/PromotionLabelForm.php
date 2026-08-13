<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PromotionLabels\Schemas;

use App\Models\Language;
use App\Services\Advertising\CardDecorationService;
use App\Support\Advertising\CardPosition;
use App\Support\Advertising\PromotionLabelDecoration;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * The live preview at the bottom of this form is not a cosmetic extra: the
 * specification requires an administrator to see the decorated card before
 * saving it, so every field the preview reads is marked `->live()` and the
 * preview itself is rebuilt, on every change, through the same
 * {@see CardDecorationService::previewLabel()} the runtime decoration
 * payload uses — no separate rendering logic to drift out of step with it.
 */
class PromotionLabelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ColorPicker::make('border_colour')
                ->label(__('panel.promotion_labels.form.border_colour'))
                ->live()
                ->required(),

            ColorPicker::make('text_colour')
                ->label(__('panel.promotion_labels.form.text_colour'))
                ->live()
                ->required(),

            ColorPicker::make('background_colour')
                ->label(__('panel.promotion_labels.form.background_colour'))
                ->live()
                ->required(),

            TextInput::make('icon')
                ->label(__('panel.promotion_labels.form.icon'))
                ->live()
                ->maxLength(255),

            Select::make('position_on_card')
                ->label(__('panel.promotion_labels.form.position_on_card'))
                ->live()
                ->required()
                ->options(fn (): array => collect(CardPosition::cases())
                    ->mapWithKeys(fn (CardPosition $position): array => [
                        $position->value => __("panel.promotion_labels.positions.{$position->value}"),
                    ])
                    ->all()),

            Toggle::make('is_active')->label(__('panel.promotion_labels.columns.active'))->default(true),

            ...self::perLanguageSections(),

            Section::make(__('panel.promotion_labels.form.preview'))
                ->schema([
                    Html::make(fn (Get $get): HtmlString => self::renderPreview($get)),
                ]),
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
                        TextInput::make("translations.{$language->code}.text")
                            ->label(__('panel.promotion_labels.form.text'))
                            ->live()
                            ->required()
                            ->maxLength(255),
                    ]))
                ->all(),
        );
    }

    private static function renderPreview(Get $get): HtmlString
    {
        try {
            $decoration = app(CardDecorationService::class)->previewLabel([
                'border_colour' => $get('border_colour'),
                'text_colour' => $get('text_colour'),
                'background_colour' => $get('background_colour'),
                'icon' => $get('icon'),
                'position_on_card' => $get('position_on_card'),
                'text' => self::firstTranslatedText($get('translations')),
            ]);
        } catch (InvalidArgumentException) {
            return new HtmlString(
                '<p style="font-size:0.875rem; opacity:0.7;">'.e(__('panel.promotion_labels.form.preview_placeholder')).'</p>',
            );
        }

        return self::decorationToHtml($decoration);
    }

    private static function firstTranslatedText(mixed $translations): ?string
    {
        if (! is_array($translations)) {
            return null;
        }

        foreach ($translations as $fields) {
            $text = is_array($fields) ? ($fields['text'] ?? null) : null;

            if (is_string($text) && $text !== '') {
                return $text;
            }
        }

        return null;
    }

    private static function decorationToHtml(PromotionLabelDecoration $decoration): HtmlString
    {
        $border = e($decoration->borderColour !== '' ? $decoration->borderColour : '#d1d5db');
        $background = e($decoration->backgroundColour !== '' ? $decoration->backgroundColour : '#ffffff');
        $text = e($decoration->textColour !== '' ? $decoration->textColour : '#111827');
        $label = e($decoration->text ?? __('panel.promotion_labels.form.preview_sample_text'));
        $icon = $decoration->icon !== null ? e($decoration->icon).' ' : '';
        $position = e(__("panel.promotion_labels.positions.{$decoration->position->value}"));

        return new HtmlString(
            '<div style="border:2px solid '.$border.'; border-radius:0.5rem; padding:1rem; max-width:20rem;">'
            .'<span style="display:inline-block; background-color:'.$background.'; color:'.$text.'; '
            .'padding:0.25rem 0.6rem; border-radius:0.35rem; font-size:0.75rem; font-weight:600;">'
            .$icon.$label
            .'</span>'
            .'<p style="margin-top:0.5rem; font-size:0.75rem; opacity:0.7;">'.$position.'</p>'
            .'</div>',
        );
    }
}
