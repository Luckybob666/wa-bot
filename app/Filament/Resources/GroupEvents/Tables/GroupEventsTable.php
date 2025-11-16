<?php

namespace App\Filament\Resources\GroupEvents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\GroupEvent;

class GroupEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bot.name')
                    ->label('所属机器人')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('group.name')
                    ->label('群名称')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user_display')
                    ->label('用户')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function (GroupEvent $record) {
                        // 优先从 event_data 中获取用户信息
                        $eventData = $record->event_data ?? [];
                        $phoneNumber = $eventData['phone_number'] ?? null;
                        
                        if ($phoneNumber) {
                            return $phoneNumber;
                        }
                        
                        // 如果没有，从关系获取
                        if ($record->whatsappUser) {
                            return $record->whatsappUser->phone_number ?: $record->whatsappUser->display_name;
                        }
                        
                        return '系统事件';
                    })
                    ->placeholder('系统事件'),
                TextColumn::make('event_type')
                    ->label('事件类型')
                    ->badge()
                    ->color(fn (GroupEvent $record): string => $record->color)
                    ->formatStateUsing(fn (GroupEvent $record): string => $record->event_type_label),
                TextColumn::make('description')
                    ->label('描述')
                    ->getStateUsing(fn (GroupEvent $record): string => $record->description)
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                TextColumn::make('created_at')
                    ->label('发生时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at->format('Y-m-d H:i:s')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event_type')
                    ->label('事件类型')
                    ->options(GroupEvent::getEventTypeOptions()),
                SelectFilter::make('bot_id')
                    ->label('所属机器人')
                    ->relationship('bot', 'name')
                    ->searchable()
                    ->preload(),
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
