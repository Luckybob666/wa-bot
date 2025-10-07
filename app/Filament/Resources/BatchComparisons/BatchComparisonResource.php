<?php

namespace App\Filament\Resources\BatchComparisons;

use App\Filament\Resources\BatchComparisons\Pages\CreateBatchComparison;
use App\Filament\Resources\BatchComparisons\Pages\EditBatchComparison;
use App\Filament\Resources\BatchComparisons\Pages\ListBatchComparisons;
use App\Filament\Resources\BatchComparisons\Pages\ViewBatchComparison;
use App\Filament\Resources\BatchComparisons\Schemas\BatchComparisonForm;
use App\Filament\Resources\BatchComparisons\Schemas\BatchComparisonInfolist;
use App\Filament\Resources\BatchComparisons\Tables\BatchComparisonsTable;
use App\Models\BatchComparison;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BatchComparisonResource extends Resource
{
    protected static ?string $model = BatchComparison::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return BatchComparisonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BatchComparisonInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BatchComparisonsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatchComparisons::route('/'),
            'create' => CreateBatchComparison::route('/create'),
            'view' => ViewBatchComparison::route('/{record}'),
            'edit' => EditBatchComparison::route('/{record}/edit'),
        ];
    }
}
