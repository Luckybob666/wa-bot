<?php

namespace App\Filament\Resources\BatchComparisons\Tables;

use App\Models\BatchComparison;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Response;

class BatchComparisonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('phoneBatch.name')
                    ->label('批次名称')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('批次名称已复制'),
                TextColumn::make('group.name')
                    ->label('群组名称')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('群组名称已复制'),
                TextColumn::make('matched_count')
                    ->label('已进群数量')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->description(fn (BatchComparison $record): string => 
                        "批次中的号码已在群里"
                    ),
                TextColumn::make('unmatched_count')
                    ->label('未进群数量')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->description(fn (BatchComparison $record): string => 
                        "批次中的号码不在群里"
                    ),
                TextColumn::make('extra_count')
                    ->label('群里多出')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->description(fn (BatchComparison $record): string => 
                        "群里的号码不在批次中"
                    ),
                TextColumn::make('match_rate')
                    ->label('匹配率')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => $state . '%'),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => 
                        BatchComparison::getStatusOptions()[$state] ?? $state
                    ),
                TextColumn::make('completed_at')
                    ->label('完成时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->placeholder('未完成'),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(BatchComparison::getStatusOptions()),
                SelectFilter::make('phone_batch_id')
                    ->label('批次')
                    ->relationship('phoneBatch', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('group_id')
                    ->label('群组')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('export_csv')
                    ->label('导出CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (BatchComparison $record) {
                        if (!$record->isCompleted()) {
                            Notification::make()
                                ->title('无法导出')
                                ->body('只有已完成的比对结果才能导出')
                                ->warning()
                                ->send();
                            return;
                        }

                        $csv = $record->exportToCsv();
                        $filename = sprintf(
                            '比对结果_%s_%s_%s.csv',
                            $record->phoneBatch->name ?? 'batch',
                            $record->group->name ?? 'group',
                            now()->format('YmdHis')
                        );

                        return Response::streamDownload(function () use ($csv) {
                            echo $csv;
                        }, $filename, [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                        ]);
                    })
                    ->visible(fn (BatchComparison $record): bool => $record->isCompleted()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
