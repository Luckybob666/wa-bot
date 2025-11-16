<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Models\Bot;
use App\Models\Group;
use App\Models\PhoneBatch;
use App\Services\ComparisonService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bot.name')
                    ->label('所属机器人')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('群名称')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('群名称已复制'),
                TextColumn::make('group_id')
                    ->label('群 ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('群 ID 已复制'),
                TextColumn::make('status')
                    ->label('群状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'removed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '活跃',
                        'removed' => '已退出',
                        default => '未知',
                    })
                    ->sortable(),
                TextColumn::make('current_member_count')
                    ->label('当前人数')
                    ->numeric()
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('member_count', $direction);
                    })
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn (Group $record): int => $record->getCurrentMemberCount()),
                TextColumn::make('original_member_count')
                    ->label('原有人数')
                    ->numeric()
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('initial_member_count', $direction);
                    })
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Group $record): int => $record->getOriginalMemberCount())
                    ->placeholder('未记录'),
                TextColumn::make('new_joined_count')
                    ->label('新进群人数')
                    ->numeric()
                    ->badge()
                    ->color('warning')
                    ->tooltip('包含已退出的成员')
                    ->getStateUsing(fn (Group $record): int => $record->getNewJoinedMemberCount()),
                TextColumn::make('left_count')
                    ->label('退出人数')
                    ->numeric()
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Group $record): int => $record->getLeftMemberCount()),
                TextColumn::make('removed_count')
                    ->label('被移除用户')
                    ->numeric()
                    ->badge()
                    ->color('danger')
                    ->getStateUsing(fn (Group $record): int => $record->getRemovedMemberCount()),
                TextColumn::make('phoneBatch.name')
                    ->label('绑定批次')
                    ->placeholder('未绑定')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'gray'),
                TextColumn::make('matched_count')
                    ->label('已进群数量')
                    ->numeric()
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->default(0),
                TextColumn::make('unmatched_count')
                    ->label('未进群数量')
                    ->numeric()
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->default(0),
                TextColumn::make('extra_count')
                    ->label('群里多出')
                    ->numeric()
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->default(0),
                TextColumn::make('match_rate')
                    ->label('匹配率')
                    ->numeric()
                    ->suffix('%')
                    ->badge()
                    ->color(fn (float $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->sortable()
                    ->default(0),
                TextColumn::make('updated_at')
                    ->label('最后更新')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('描述')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('status')
                    ->label('群状态')
                    ->options([
                        'active' => '活跃',
                        'removed' => '已退出',
                    ]),
                SelectFilter::make('phone_batch_id')
                    ->label('是否绑定批次')
                    ->options([
                        '1' => '已绑定',
                        '0' => '未绑定',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === '1') {
                            return $query->whereNotNull('phone_batch_id');
                        } elseif ($data['value'] === '0') {
                            return $query->whereNull('phone_batch_id');
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
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
                    ->action(function (Group $record, array $data) {
                        $batch = PhoneBatch::find($data['phone_batch_id']);
                        if ($batch) {
                            // 绑定批次（内部会调用 updateBatchComparison）
                            $record->bindBatch($batch);
                            
                            // 刷新记录以获取最新的比对数据
                            $record->refresh();
                            
                            // 获取比对结果用于显示
                            $matchedCount = $record->matched_count ?? 0;
                            $unmatchedCount = $record->unmatched_count ?? 0;
                            $extraCount = $record->extra_count ?? 0;
                            $matchRate = $record->match_rate ?? 0;
                            
                            Notification::make()
                                ->title('绑定成功')
                                ->body("群组已绑定到批次：{$batch->name}。比对结果：已进群 {$matchedCount}，未进群 {$unmatchedCount}，群里多出 {$extraCount}，匹配率 {$matchRate}%")
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
                
                Action::make('export_csv')
                    ->label('导出CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (Group $record) {
                        try {
                            $csvContent = $record->exportUsersToCsv();
                            $fileName = '群组_' . $record->name . '_' . date('Y-m-d_H-i-s') . '.csv';
                            
                            return response()->streamDownload(function () use ($csvContent) {
                                echo $csvContent;
                            }, $fileName, [
                                'Content-Type' => 'text/csv; charset=UTF-8',
                                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                            ]);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('导出失败')
                                ->body('导出过程中发生错误：' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
