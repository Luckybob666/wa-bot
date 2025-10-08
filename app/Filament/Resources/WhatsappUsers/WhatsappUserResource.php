<?php

namespace App\Filament\Resources\WhatsappUsers;

use App\Filament\Resources\WhatsappUsers\Pages\CreateWhatsappUser;
use App\Filament\Resources\WhatsappUsers\Pages\EditWhatsappUser;
use App\Filament\Resources\WhatsappUsers\Pages\ListWhatsappUsers;
use App\Filament\Resources\WhatsappUsers\Pages\ViewWhatsappUser;
use App\Filament\Resources\WhatsappUsers\Schemas\WhatsappUserForm;
use App\Filament\Resources\WhatsappUsers\Schemas\WhatsappUserInfolist;
use App\Filament\Resources\WhatsappUsers\Tables\WhatsappUsersTable;
use App\Models\WhatsappUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WhatsappUserResource extends Resource
{
    protected static ?string $model = WhatsappUser::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $recordTitleAttribute = 'nickname';

    protected static ?string $navigationLabel = 'WhatsApp用户管理';

    protected static ?string $modelLabel = 'WhatsApp用户';

    protected static ?string $pluralModelLabel = 'WhatsApp用户';

    public static function form(Schema $schema): Schema
    {
        return WhatsappUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WhatsappUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappUsersTable::configure($table);
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
            'index' => ListWhatsappUsers::route('/'),
            'create' => CreateWhatsappUser::route('/create'),
            'view' => ViewWhatsappUser::route('/{record}'),
            'edit' => EditWhatsappUser::route('/{record}/edit'),
        ];
    }
}
