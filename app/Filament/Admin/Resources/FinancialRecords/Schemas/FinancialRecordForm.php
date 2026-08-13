<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords\Schemas;

use App\Models\Object_;
use App\Models\PlacementPackage;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Manual, administrator-entered records per §122 — this form is the
 * primary way a real payment (as opposed to `PlacementLifecycleService`'s
 * automatic `granted_free` write on every grant) enters the ledger.
 * Exactly one of object/banner is required, matching the table's own
 * check constraint.
 */
class FinancialRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Radio::make('subject_kind')
                ->label(__('panel.financial_records.form.subject_kind'))
                ->options([
                    'object' => __('panel.financial_records.form.subject_object'),
                    'banner' => __('panel.financial_records.form.subject_banner'),
                ])
                ->default('object')
                ->live()
                ->dehydrated(false)
                ->required(),

            Select::make('object_id')
                ->label(__('panel.financial_records.form.object'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Object_::query()
                    ->withoutGlobalScopes()
                    ->where('id', 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck('id', 'id')
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => Object_::query()->withoutGlobalScopes()->whereKey($value)->exists() ? "#{$value}" : null)
                ->required(fn (Get $get): bool => $get('subject_kind') === 'object')
                ->visible(fn (Get $get): bool => $get('subject_kind') === 'object'),

            // A plain numeric entry rather than a searchable Select: no
            // Banner model exists yet to look options up through a
            // service (Filament resources reach the database only
            // through services, never the DB facade directly) — this
            // upgrades to a proper Select once the advertising track
            // ships one.
            TextInput::make('banner_id')
                ->label(__('panel.financial_records.form.banner'))
                ->numeric()
                ->required(fn (Get $get): bool => $get('subject_kind') === 'banner')
                ->visible(fn (Get $get): bool => $get('subject_kind') === 'banner'),

            TextInput::make('service')
                ->label(__('panel.financial_records.form.service'))
                ->required()
                ->maxLength(255),

            Select::make('placement_package_id')
                ->label(__('panel.financial_records.form.package'))
                ->searchable()
                ->options(fn (): array => PlacementPackage::query()->with('translations')->get()
                    ->mapWithKeys(fn (PlacementPackage $package): array => [$package->id => $package->name ?? "#{$package->id}"])
                    ->all()),

            TextInput::make('amount')
                ->label(__('panel.financial_records.form.amount'))
                ->required()
                ->numeric()
                ->minValue(0),

            TextInput::make('currency')
                ->label(__('panel.financial_records.form.currency'))
                ->required()
                ->maxLength(3)
                ->alpha()
                ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper((string) $state)),

            Select::make('status')
                ->label(__('panel.financial_records.form.status'))
                ->options([
                    'awaiting_payment' => __('panel.financial_records.status.awaiting_payment'),
                    'paid' => __('panel.financial_records.status.paid'),
                    'partially_paid' => __('panel.financial_records.status.partially_paid'),
                    'overdue' => __('panel.financial_records.status.overdue'),
                    'cancelled' => __('panel.financial_records.status.cancelled'),
                    'granted_free' => __('panel.financial_records.status.granted_free'),
                ])
                ->required(),

            DateTimePicker::make('paid_at')->label(__('panel.financial_records.form.paid_at')),
            DatePicker::make('valid_from')->label(__('panel.financial_records.form.valid_from')),
            DatePicker::make('valid_until')->label(__('panel.financial_records.form.valid_until')),
            TextInput::make('payment_method')->label(__('panel.financial_records.form.payment_method'))->maxLength(255),
            TextInput::make('document_number')->label(__('panel.financial_records.form.document_number'))->maxLength(255),

            Select::make('responsible_staff_id')
                ->label(__('panel.financial_records.form.responsible_staff'))
                ->searchable()
                ->options(fn (): array => User::query()->whereHas('roles')->pluck('name', 'id')->all()),

            Textarea::make('comment')->label(__('panel.financial_records.form.comment'))->columnSpanFull(),
        ]);
    }
}
