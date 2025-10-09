<?php

namespace App\Filament\Resources\BatchComparisons\Schemas;

use App\Models\BatchComparison;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BatchComparisonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        TextEntry::make('phoneBatch.name')
                            ->label('批次名称'),
                        TextEntry::make('group.name')
                            ->label('群组名称'),
                        TextEntry::make('status')
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
                        TextEntry::make('completed_at')
                            ->label('完成时间')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('未完成'),
                    ])
                    ->columns(2),
                
                Section::make('比对统计')
                    ->schema([
                        TextEntry::make('matched_count')
                            ->label('已进群数量')
                            ->badge()
                            ->color('success')
                            ->suffix(' 个')
                            ->description('批次中的号码已在群里'),
                        TextEntry::make('unmatched_count')
                            ->label('未进群数量')
                            ->badge()
                            ->color('warning')
                            ->suffix(' 个')
                            ->description('批次中的号码不在群里'),
                        TextEntry::make('extra_count')
                            ->label('群里多出')
                            ->badge()
                            ->color('info')
                            ->suffix(' 个')
                            ->description('群里的号码不在批次中'),
                        TextEntry::make('match_rate')
                            ->label('匹配率')
                            ->badge()
                            ->color(fn ($state): string => match (true) {
                                $state >= 80 => 'success',
                                $state >= 50 => 'warning',
                                default => 'danger',
                            })
                            ->formatStateUsing(fn ($state) => $state . '%'),
                    ])
                    ->columns(4),
                
                Section::make('已进群号码')
                    ->schema([
                        TextEntry::make('matched_numbers')
                            ->label('')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('无')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn (BatchComparison $record): bool => 
                        $record->isCompleted() && !empty($record->getMatchedNumbers())
                    ),
                
                Section::make('未进群号码')
                    ->schema([
                        TextEntry::make('unmatched_numbers')
                            ->label('')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('无')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn (BatchComparison $record): bool => 
                        $record->isCompleted() && !empty($record->getUnmatchedNumbers())
                    ),
                
                Section::make('群里多出号码')
                    ->schema([
                        TextEntry::make('extra_numbers')
                            ->label('')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('无')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn (BatchComparison $record): bool => 
                        $record->isCompleted() && !empty($record->getExtraNumbers())
                    ),
                
                Section::make('时间信息')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('创建时间')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('updated_at')
                            ->label('更新时间')
                            ->dateTime('Y-m-d H:i:s'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
