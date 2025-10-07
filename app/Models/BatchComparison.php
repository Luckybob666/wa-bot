<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchComparison extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_batch_id',
        'group_id',
        'matched_numbers',
        'unmatched_numbers',
        'matched_count',
        'unmatched_count',
        'match_rate',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'matched_numbers' => 'array',
        'unmatched_numbers' => 'array',
        'matched_count' => 'integer',
        'unmatched_count' => 'integer',
        'match_rate' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    // 状态常量
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * 获取状态选项
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => '待比对',
            self::STATUS_PROCESSING => '比对中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败',
        ];
    }

    /**
     * 获取状态标签
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? '未知';
    }

    /**
     * 获取匹配率显示
     */
    public function getMatchRateDisplayAttribute(): string
    {
        return $this->match_rate . '%';
    }

    /**
     * 获取总数量
     */
    public function getTotalCountAttribute(): int
    {
        return $this->matched_count + $this->unmatched_count;
    }

    /**
     * 检查是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 检查是否正在处理
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * 检查是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 关联手机号批次
     */
    public function phoneBatch(): BelongsTo
    {
        return $this->belongsTo(PhoneBatch::class);
    }

    /**
     * 关联群组
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * 获取已匹配的手机号列表
     */
    public function getMatchedNumbers(): array
    {
        return $this->matched_numbers ?? [];
    }

    /**
     * 获取未匹配的手机号列表
     */
    public function getUnmatchedNumbers(): array
    {
        return $this->unmatched_numbers ?? [];
    }

    /**
     * 设置比对结果
     */
    public function setComparisonResults(array $matchedNumbers, array $unmatchedNumbers): void
    {
        $this->matched_numbers = $matchedNumbers;
        $this->unmatched_numbers = $unmatchedNumbers;
        $this->matched_count = count($matchedNumbers);
        $this->unmatched_count = count($unmatchedNumbers);
        
        $totalCount = $this->matched_count + $this->unmatched_count;
        $this->match_rate = $totalCount > 0 ? round(($this->matched_count / $totalCount) * 100, 2) : 0;
        
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
    }

    /**
     * 标记为处理中
     */
    public function markAsProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->save();
    }

    /**
     * 标记为失败
     */
    public function markAsFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->save();
    }

    /**
     * 查询作用域：按状态筛选
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 查询作用域：按群组筛选
     */
    public function scopeByGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * 查询作用域：按批次筛选
     */
    public function scopeByBatch($query, int $batchId)
    {
        return $query->where('phone_batch_id', $batchId);
    }
}