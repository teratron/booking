<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ErrorPages\Schemas;

use App\Filament\Cabinet\Resources\Rooms\Schemas\RoomForm;
use App\Models\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Translated fields are collected as nested form state
 * (`translations.{locale}.*`) and reconciled against the real
 * `error_page_translations` rows on the create/edit pages — astrotomic's
 * translation package has no Filament relationship binding of its own to
 * lean on, the same mechanism {@see RoomForm}
 * uses for its own name and description.
 */
class ErrorPageForm
{
    /**
     * The only status code with a live custom template today — see
     * `resources/views/errors/404.blade.php`. Extending coverage to another
     * status code means adding that Blade view first; this option set stays
     * a closed vocabulary of the ones actually rendered from this table.
     */
    private const array STATUS_CODES = [404];

    public static function configure(Schema $schema): Schema
    {
        $primaryLocale = Language::query()->where('is_primary', true)->value('code');

        return $schema->components([
            Select::make('status_code')
                ->label(__('panel.error_pages.form.status_code'))
                ->options(array_combine(
                    self::STATUS_CODES,
                    array_map(static fn (int $code): string => __("panel.error_pages.status_names.{$code}"), self::STATUS_CODES),
                ))
                ->required()
                ->unique(ignoreRecord: true),

            Section::make(__('panel.error_pages.form.translations'))
                ->schema(self::perLanguageSections($primaryLocale)),
        ]);
    }

    /** @return list<Section> */
    private static function perLanguageSections(?string $primaryLocale): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn (Language $language): Section => Section::make($language->short_label)
                    ->schema([
                        TextInput::make("translations.{$language->code}.title")
                            ->label(__('panel.error_pages.form.title'))
                            ->maxLength(255)
                            ->required($language->code === $primaryLocale),

                        Textarea::make("translations.{$language->code}.body")
                            ->label(__('panel.error_pages.form.body'))
                            ->required($language->code === $primaryLocale),
                    ])
                    ->collapsible())
                ->all(),
        );
    }
}
