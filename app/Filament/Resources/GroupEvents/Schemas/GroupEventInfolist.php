<?php

namespace App\Filament\Resources\GroupEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GroupEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('bot.name')
                    ->numeric(),
                TextEntry::make('group.name')
                    ->numeric(),
                TextEntry::make('whatsappUser.id')
                    ->numeric(),
                TextEntry::make('event_type'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
