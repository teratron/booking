<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SeoMetadataTemplates;

use App\Filament\Admin\Resources\Redirects\RedirectResource;
use App\Filament\Admin\Resources\SeoMetadataTemplates\Pages\CreateSeoMetadataTemplate;
use App\Filament\Admin\Resources\SeoMetadataTemplates\Pages\EditSeoMetadataTemplate;
use App\Filament\Admin\Resources\SeoMetadataTemplates\Pages\ListSeoMetadataTemplates;
use App\Filament\Admin\Resources\SeoMetadataTemplates\Schemas\SeoMetadataTemplateForm;
use App\Filament\Admin\Resources\SeoMetadataTemplates\Tables\SeoMetadataTemplatesTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\SeoMetadataTemplate;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The middle rung of the metadata resolution ladder's own administration
 * screen — portal-wide configuration, not scoped to a country, territory,
 * or category, the same posture {@see RedirectResource}
 * takes for the redirect table.
 */
class SeoMetadataTemplateResource extends ScopedResource
{
    protected static ?string $model = SeoMetadataTemplate::class;

    protected static string $permissionPrefix = 'seo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'seo';

    public static function getNavigationLabel(): string
    {
        return __('panel.seo_metadata_templates.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.seo_metadata_templates.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.seo_metadata_templates.title');
    }

    public static function form(Schema $schema): Schema
    {
        return SeoMetadataTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoMetadataTemplatesTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListSeoMetadataTemplates::route('/'),
            'create' => CreateSeoMetadataTemplate::route('/create'),
            'edit' => EditSeoMetadataTemplate::route('/{record}/edit'),
        ];
    }
}
