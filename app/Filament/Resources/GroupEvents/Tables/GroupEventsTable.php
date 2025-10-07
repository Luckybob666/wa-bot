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
                TextColumn::make('whatsappUser.nickname')
                    ->label('用户')
                    ->sortable()
                    ->searchable()
                    ->placeholder('系统事件'),
                TextColumn::make('event_type')
                    ->label('事件类型')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GroupEvent::EVENT_MEMBER_JOINED => 'success',
                        GroupEvent::EVENT_MEMBER_LEFT => 'danger',
                        GroupEvent::EVENT_GROUP_UPDATED => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        GroupEvent::EVENT_MEMBER_JOINED => '成员加入',
                        GroupEvent::EVENT_MEMBER_LEFT => '成员离开',
                        GroupEvent::EVENT_GROUP_UPDATED => '群信息更新',
                        default => $state,
                    }),
                TextColumn::make('description')
                    ->label('描述')
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
            ->filters([
                SelectFilter::make('event_type')
                    ->label('事件类型')
                    ->options([
                        GroupEvent::EVENT_MEMBER_JOINED => '成员加入',
                        GroupEvent::EVENT_MEMBER_LEFT => '成员离开',
                        GroupEvent::EVENT_GROUP_UPDATED => '群信息更新',
                    ]),
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
