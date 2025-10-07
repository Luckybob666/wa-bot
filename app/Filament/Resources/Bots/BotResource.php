<?php

namespace App\Filament\Resources\Bots;

use App\Filament\Resources\Bots\Pages\CreateBot;
use App\Filament\Resources\Bots\Pages\EditBot;
use App\Filament\Resources\Bots\Pages\ListBots;
use App\Filament\Resources\Bots\Pages\ViewBot;
use App\Filament\Resources\Bots\Pages\ConnectBot;
use App\Filament\Resources\Bots\Schemas\BotForm;
use App\Filament\Resources\Bots\Schemas\BotInfolist;
use App\Filament\Resources\Bots\Tables\BotsTable;
use App\Models\Bot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BotResource extends Resource
{
    protected static ?string $model = Bot::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = '机器人管理';

    protected static ?string $modelLabel = '机器人';

    protected static ?string $pluralModelLabel = '机器人';

    public static function form(Schema $schema): Schema
    {
        return BotForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BotsTable::configure($table);
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
            'index' => ListBots::route('/'),
            'create' => CreateBot::route('/create'),
            'view' => ViewBot::route('/{record}'),
            'edit' => EditBot::route('/{record}/edit'),
            'connect' => ConnectBot::route('/{record}/connect'),
        ];
    }
}
