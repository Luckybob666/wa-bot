<?php

namespace App\Filament\Resources\WhatsappUsers\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile_picture')
                    ->label('头像')
                    ->circular()
                    ->defaultImageUrl(function ($record) {
                        // 使用用户名首字母生成默认头像
                        $initials = strtoupper(substr($record->nickname ?? $record->phone_number, 0, 2));
                        return "https://ui-avatars.com/api/?name={$initials}&background=6366f1&color=ffffff&size=40";
                    }),
                TextColumn::make('nickname')
                    ->label('昵称')
                    ->searchable()
                    ->sortable()
                    ->placeholder('未设置昵称'),
                TextColumn::make('phone_number')
                    ->label('手机号')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('手机号已复制'),
                
                TextColumn::make('group_name')
                    ->label('所属群名字')
                    ->searchable()
                    ->sortable()
                    ->placeholder('未设置'),
                
                TextColumn::make('bot.name')
                    ->label('所属机器人')
                    ->searchable()
                    ->sortable()
                    ->placeholder('未设置'),
                
                TextColumn::make('group_id')
                    ->label('所属群ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('群组ID已复制')
                    ->placeholder('未设置'),
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
                // 可以添加其他过滤器，如按机器人筛选
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('fetch_user_details')
                    ->label('获取用户资料')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->visible(fn ($record) => empty($record->nickname) && empty($record->profile_picture))
                    ->action(function ($record) {
                        // TODO: 实现获取用户详细信息的逻辑
                        Notification::make()
                            ->title('功能开发中')
                            ->body('获取用户资料功能正在开发中')
                            ->info()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
