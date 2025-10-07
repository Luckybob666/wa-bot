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
                    ->formatStateUsing(function ($record) {
                        // 使用格式化后的手机号显示
                        $formatted = $record->formatted_phone_number;
                        
                        // 如果是异常格式，添加警告图标
                        if ($record->hasAbnormalPhoneNumber()) {
                            return $formatted . ' ⚠️';
                        }
                        
                        return $formatted;
                    })
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('手机号已复制')
                    ->tooltip(function ($record) {
                        if ($record->hasAbnormalPhoneNumber()) {
                            return '原始手机号: ' . $record->phone_number . "\n⚠️ 检测到异常格式";
                        }
                        return '原始手机号: ' . $record->phone_number;
                    }),
                TextColumn::make('group_id')
                    ->label('群组ID')
                    ->formatStateUsing(function ($record) {
                        try {
                            $groups = $record->groups()->get();
                            if ($groups->isEmpty()) {
                                return '未加入任何群组';
                            }
                            
                            return $groups->map(function ($group) {
                                return $group->group_id;
                            })->join(', ');
                        } catch (\Exception $e) {
                            return '错误: ' . $e->getMessage();
                        }
                    })
                    ->copyable()
                    ->copyMessage('群组ID已复制')
                    ->searchable(),
                
                TextColumn::make('group_name')
                    ->label('群组名称')
                    ->formatStateUsing(function ($record) {
                        try {
                            $groups = $record->groups()->with('bot')->get();
                            if ($groups->isEmpty()) {
                                return '未加入任何群组';
                            }
                            
                            return $groups->map(function ($group) {
                                $botName = $group->bot->name ?? '未知机器人';
                                return "{$group->name} ({$botName})";
                            })->join(', ');
                        } catch (\Exception $e) {
                            return '错误: ' . $e->getMessage();
                        }
                    })
                    ->wrap()
                    ->limit(50)
                    ->tooltip(function ($record) {
                        try {
                            $groups = $record->groups()->with('bot')->get();
                            if ($groups->isEmpty()) {
                                return null;
                            }
                            
                            return $groups->map(function ($group) {
                                $botName = $group->bot->name ?? '未知机器人';
                                return "群组: {$group->name}\n机器人: {$botName}\n群组ID: {$group->group_id}";
                            })->join("\n\n");
                        } catch (\Exception $e) {
                            return '错误: ' . $e->getMessage();
                        }
                    }),
                
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn ($record) => $record->groups()->count() > 0 ? 'success' : 'warning')
                    ->formatStateUsing(fn ($record) => $record->groups()->count() > 0 ? '已进群' : '未进群'),
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
                    ->label('进群状态')
                    ->options([
                        'joined' => '已进群',
                        'not_joined' => '未进群',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'joined') {
                            return $query->has('groups');
                        } elseif ($data['value'] === 'not_joined') {
                            return $query->doesntHave('groups');
                        }
                        return $query;
                    }),
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
