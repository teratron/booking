<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SeoMetadataTemplates\Tables;

use App\Support\Seo\SeoEntityType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeoMetadataTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_type')
                    ->label(__('panel.seo_metadata_templates.form.entity_type'))
                    ->formatStateUsing(fn (string $state): string => __("panel.seo_metadata_templates.form.entity_types.{$state}"))
                    ->badge()
                    ->sortable(),

                TextColumn::make('locale')
                    ->label(__('panel.seo_metadata_templates.form.locale'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('field')
                    ->label(__('panel.seo_metadata_templates.form.field'))
                    ->formatStateUsing(fn (string $state): string => __("panel.seo_metadata_templates.form.fields.{$state}"))
                    ->sortable(),

                TextColumn::make('template')
                    ->label(__('panel.seo_metadata_templates.form.template'))
                    ->searchable()
                    ->limit(60),

                TextColumn::make('updated_at')
                    ->label(__('panel.seo_metadata_templates.columns.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->label(__('panel.seo_metadata_templates.form.entity_type'))
                    ->options(fn (): array => collect(SeoEntityType::cases())
                        ->mapWithKeys(fn (SeoEntityType $case): array => [$case->value => __("panel.seo_metadata_templates.form.entity_types.{$case->value}")])
                        ->all()),
                SelectFilter::make('locale')
                    ->label(__('panel.seo_metadata_templates.form.locale'))
                    ->relationship('language', 'short_label'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('entity_type');
    }
}
