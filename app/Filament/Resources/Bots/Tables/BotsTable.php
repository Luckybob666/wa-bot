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
                // 查看/管理按钮 - 整合了连接功能
                ViewAction::make()
                    ->label(fn (Bot $record): string => match ($record->status) {
                        'offline' => '连接',
                        'online' => '管理',
                        default => '查看'
                    })
                    ->icon(fn (Bot $record): string => match ($record->status) {
                        'offline' => 'heroicon-o-qr-code',
                        'online' => 'heroicon-o-cog-6-tooth',
                        default => 'heroicon-o-eye'
                    })
                    ->color(fn (Bot $record): string => match ($record->status) {
                        'offline' => 'success',
                        'online' => 'info',
                        default => 'gray'
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
