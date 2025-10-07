<?php

namespace App\Filament\Resources\PhoneBatches;

use App\Filament\Resources\PhoneBatches\Pages\CreatePhoneBatch;
use App\Filament\Resources\PhoneBatches\Pages\EditPhoneBatch;
use App\Filament\Resources\PhoneBatches\Pages\ListPhoneBatches;
use App\Filament\Resources\PhoneBatches\Pages\ViewPhoneBatch;
use App\Filament\Resources\PhoneBatches\Schemas\PhoneBatchForm;
use App\Filament\Resources\PhoneBatches\Schemas\PhoneBatchInfolist;
use App\Filament\Resources\PhoneBatches\Tables\PhoneBatchesTable;
use App\Models\PhoneBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PhoneBatchResource extends Resource
{
    protected static ?string $model = PhoneBatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';

    protected static ?string $recordTitleAttribute = 'name';
    
    protected static ?string $navigationLabel = '手机号批次';
    
    protected static ?string $modelLabel = '手机号批次';
    
    protected static ?string $pluralModelLabel = '手机号批次';

    public static function form(Schema $schema): Schema
    {
        return PhoneBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PhoneBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PhoneBatchesTable::configure($table);
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
            'index' => ListPhoneBatches::route('/'),
            'create' => CreatePhoneBatch::route('/create'),
            'view' => ViewPhoneBatch::route('/{record}'),
            'edit' => EditPhoneBatch::route('/{record}/edit'),
        ];
    }
}
