<?php

namespace App\Filament\Resources\Bots\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('phone_number'),
                TextEntry::make('status'),
                TextEntry::make('last_seen')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
