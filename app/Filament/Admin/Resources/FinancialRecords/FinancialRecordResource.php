<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FinancialRecords;

use App\Filament\Admin\Resources\FinancialRecords\Pages\CreateFinancialRecord;
use App\Filament\Admin\Resources\FinancialRecords\Pages\EditFinancialRecord;
use App\Filament\Admin\Resources\FinancialRecords\Pages\ListFinancialRecords;
use App\Filament\Admin\Resources\FinancialRecords\Schemas\FinancialRecordForm;
use App\Filament\Admin\Resources\FinancialRecords\Tables\FinancialRecordsTable;
use App\Filament\Admin\Support\ScopedResource;
use App\Models\FinancialRecord;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The unified financial ledger — §5.5's "a ledger, not a payment system".
 * Declares no scope axis: financial read/write is gated on the standalone
 * `finance.*` permission set, independent of an administrator's country or
 * category grants, matching `T-2A05`'s dashboard finance block.
 */
class FinancialRecordResource extends ScopedResource
{
    protected static ?string $model = FinancialRecord::class;

    protected static string $permissionPrefix = 'finance';

    /** @var list<string> */
    protected static array $eagerLoad = ['object', 'package.tier.translations', 'responsibleStaff'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'commerce';

    public static function getNavigationLabel(): string
    {
        return __('panel.financial_records.title');
    }

    public static function getModelLabel(): string
    {
        return __('panel.financial_records.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.financial_records.title');
    }

    public static function form(Schema $schema): Schema
    {
        return FinancialRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialRecordsTable::configure(self::applyTableDefaults($table));
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListFinancialRecords::route('/'),
            'create' => CreateFinancialRecord::route('/create'),
            'edit' => EditFinancialRecord::route('/{record}/edit'),
        ];
    }
}
