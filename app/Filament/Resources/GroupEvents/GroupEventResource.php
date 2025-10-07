<?php

namespace App\Filament\Resources\GroupEvents;

use App\Filament\Resources\GroupEvents\Pages\CreateGroupEvent;
use App\Filament\Resources\GroupEvents\Pages\EditGroupEvent;
use App\Filament\Resources\GroupEvents\Pages\ListGroupEvents;
use App\Filament\Resources\GroupEvents\Pages\ViewGroupEvent;
use App\Filament\Resources\GroupEvents\Schemas\GroupEventForm;
use App\Filament\Resources\GroupEvents\Schemas\GroupEventInfolist;
use App\Filament\Resources\GroupEvents\Tables\GroupEventsTable;
use App\Models\GroupEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GroupEventResource extends Resource
{
    protected static ?string $model = GroupEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $recordTitleAttribute = 'event_type';

    protected static ?string $navigationLabel = '群事件日志';

    protected static ?string $modelLabel = '群事件';

    protected static ?string $pluralModelLabel = '群事件';

    public static function form(Schema $schema): Schema
    {
        return GroupEventForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GroupEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupEventsTable::configure($table);
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
            'index' => ListGroupEvents::route('/'),
            'create' => CreateGroupEvent::route('/create'),
            'view' => ViewGroupEvent::route('/{record}'),
            'edit' => EditGroupEvent::route('/{record}/edit'),
        ];
    }
}
