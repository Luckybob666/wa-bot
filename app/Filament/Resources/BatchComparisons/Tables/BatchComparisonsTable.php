<?php

namespace App\Filament\Resources\BatchComparisons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BatchComparisonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phoneBatch.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('group.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('matched_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unmatched_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('match_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status'),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
