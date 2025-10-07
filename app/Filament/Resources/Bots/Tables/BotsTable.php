<?php

namespace App\Filament\Resources\Bots\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\Bot;

class BotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('机器人名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone_number')
                    ->label('手机号')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Bot::STATUS_ONLINE => 'success',
                        Bot::STATUS_CONNECTING => 'warning',
                        Bot::STATUS_ERROR => 'danger',
                        Bot::STATUS_OFFLINE => 'secondary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Bot::getStatusOptions()[$state] ?? $state),
                TextColumn::make('last_seen')
                    ->label('最后活跃')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->placeholder('从未活跃'),
                TextColumn::make('groups_count')
                    ->label('管理群数')
                    ->counts('groups')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(Bot::getStatusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('connect')
                    ->label('连接')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->url(fn (Bot $record): string => route('filament.admin.resources.bots.connect', $record))
                    ->visible(fn (Bot $record): bool => $record->status === 'offline'),
                Action::make('disconnect')
                    ->label('断开')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->url(fn (Bot $record): string => route('filament.admin.resources.bots.connect', $record))
                    ->visible(fn (Bot $record): bool => $record->status === 'online'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
