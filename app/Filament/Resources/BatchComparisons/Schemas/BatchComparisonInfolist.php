<?php

namespace App\Filament\Resources\BatchComparisons\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BatchComparisonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('phoneBatch.name')
                    ->numeric(),
                TextEntry::make('group.name')
                    ->numeric(),
                TextEntry::make('matched_count')
                    ->numeric(),
                TextEntry::make('unmatched_count')
                    ->numeric(),
                TextEntry::make('match_rate')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('completed_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
