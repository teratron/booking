<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SeoMetadataTemplates\Schemas;

use App\Models\Language;
use App\Support\Seo\SeoEntityType;
use App\Support\Seo\SeoMetadataField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SeoMetadataTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('entity_type')
                ->label(__('panel.seo_metadata_templates.form.entity_type'))
                ->options(array_combine(
                    array_map(fn (SeoEntityType $case): string => $case->value, SeoEntityType::cases()),
                    array_map(fn (SeoEntityType $case): string => __("panel.seo_metadata_templates.form.entity_types.{$case->value}"), SeoEntityType::cases()),
                ))
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, callable $get) => $rule->where('locale', $get('locale'))->where('field', $get('field'))),

            Select::make('locale')
                ->label(__('panel.seo_metadata_templates.form.locale'))
                ->options(fn (): array => Language::query()->where('is_active', true)->pluck('short_label', 'code')->all())
                ->required(),

            Select::make('field')
                ->label(__('panel.seo_metadata_templates.form.field'))
                ->options([
                    SeoMetadataField::Title->value => __('panel.seo_metadata_templates.form.fields.title'),
                    SeoMetadataField::Description->value => __('panel.seo_metadata_templates.form.fields.description'),
                ])
                ->required(),

            TextInput::make('template')
                ->label(__('panel.seo_metadata_templates.form.template'))
                ->helperText(__('panel.seo_metadata_templates.form.template_hint'))
                ->required()
                ->maxLength(255),
        ]);
    }
}
