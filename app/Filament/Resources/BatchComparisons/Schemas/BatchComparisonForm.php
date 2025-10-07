<?php

namespace App\Filament\Resources\BatchComparisons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BatchComparisonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('phone_batch_id')
                    ->relationship('phoneBatch', 'name')
                    ->required(),
                Select::make('group_id')
                    ->relationship('group', 'name')
                    ->required(),
                Textarea::make('matched_numbers')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('unmatched_numbers')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('matched_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('unmatched_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('match_rate')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Select::make('status')
                    ->options([
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ])
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('completed_at'),
            ]);
    }
}
