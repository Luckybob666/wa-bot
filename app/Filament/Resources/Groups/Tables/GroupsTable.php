<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Models\Bot;
use App\Models\Group;
use App\Models\PhoneBatch;
use App\Services\ComparisonService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bot.name')
                    ->label('所属机器人')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('群 ID 已复制'),
                TextColumn::make('name')
                    ->label('群名称')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('群 ID 已复制'),
                TextColumn::make('group_id')
                    ->label('群 ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('群 ID 已复制'),
                TextColumn::make('member_count')
                    ->label('成员数量')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success'),
                TextColumn::make('phoneBatch.name')
                    ->label('绑定批次')
                    ->placeholder('未绑定')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'gray'),
                TextColumn::make('auto_compare_enabled')
                    ->label('自动比对')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? '已启用' : '未启用'),
                TextColumn::make('last_sync_at')
                    ->label('最后同步')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('从未同步')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('描述')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
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
                SelectFilter::make('bot_id')
                    ->label('所属机器人')
                    ->relationship('bot', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('bind_batch')
                    ->label('绑定批次')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('选择要绑定的批次')
                    ->form([
                        Select::make('phone_batch_id')
                            ->label('选择批次')
                            ->options(function () {
                                return PhoneBatch::where('status', 'completed')
                                    ->orderBy('created_at', 'desc')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required()
                            ->placeholder('请选择批次')
                    ])
                    ->action(function (Group $record, array $data): void {
                        $batch = PhoneBatch::find($data['phone_batch_id']);
                        if ($batch) {
                            $record->bindBatch($batch);
                            
                            Notification::make()
                                ->title('绑定成功')
                                ->body("群组已绑定到批次：{$batch->name}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('绑定失败')
                                ->body('所选批次不存在')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Group $record): bool => !$record->hasBatchBinding()),
                
                Action::make('unbind_batch')
                    ->label('解绑批次')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Group $record): void {
                        $batchName = $record->phoneBatch->name ?? '未知批次';
                        $record->unbindBatch();
                        
                        Notification::make()
                            ->title('解绑成功')
                            ->body("已解绑批次：{$batchName}")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Group $record): bool => $record->hasBatchBinding()),
                
                Action::make('compare_batch')
                    ->label('开始比对')
                    ->icon('heroicon-o-chart-bar')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('开始号码比对')
                    ->modalDescription(fn (Group $record): string => 
                        "将对比批次【{$record->phoneBatch->name}】与群组【{$record->name}】中的号码"
                    )
                    ->modalSubmitActionLabel('开始比对')
                    ->action(function (Group $record): void {
                        try {
                            $comparisonService = app(ComparisonService::class);
                            $comparison = $comparisonService->compare($record->phoneBatch, $record);
                            
                            Notification::make()
                                ->title('比对完成')
                                ->body(sprintf(
                                    '已进群: %d 个，未进群: %d 个，群里多出: %d 个，匹配率: %.2f%%',
                                    $comparison->matched_count,
                                    $comparison->unmatched_count,
                                    $comparison->extra_count,
                                    $comparison->match_rate
                                ))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('比对失败')
                                ->body('比对过程中发生错误：' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Group $record): bool => $record->hasBatchBinding()),
                
                Action::make('sync_users')
                    ->label('更新群组用户')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->action(function (Group $record): void {
                        try {
                            $nodeUrl = config('app.node_server.url');
                            $timeout = config('app.node_server.timeout', 30);
                            
                            Notification::make()
                                ->title('正在同步')
                                ->body('正在获取群组用户信息，请稍候...')
                                ->info()
                                ->send();
                            
                            $response = Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $record->bot_id . '/sync-group-users', [
                                'groupId' => $record->group_id
                            ]);
                            
                            if ($response->successful()) {
                                $data = $response->json();
                                $record->updateLastSyncTime();
                                
                                $syncedCount = $data['data']['syncedCount'] ?? 0;
                                $groupName = $data['data']['groupName'] ?? '群组';
                                
                                Notification::make()
                                    ->title('用户手机号码同步成功')
                                    ->body("已同步 {$groupName} 中的 {$syncedCount} 个用户手机号码")
                                    ->success()
                                    ->send();
                            } else {
                                $errorData = $response->json();
                                $errorMessage = $errorData['message'] ?? '未知错误';
                                
                                // 如果是机器人未找到，更新机器人状态
                                if ($response->status() === 404) {
                                    $record->bot->update(['status' => 'offline']);
                                }
                                
                                Notification::make()
                                    ->title('同步失败')
                                    ->body($errorMessage)
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Illuminate\Http\Client\ConnectionException $e) {
                            Notification::make()
                                ->title('连接失败')
                                ->body('无法连接到 Node.js 服务器，请确保服务器正在运行')
                                ->danger()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('同步失败')
                                ->body('同步过程中发生错误：' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Group $record): bool => $record->bot->status === 'online'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
