<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'total_count',
        'processed_count',
        'status',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'processed_count' => 'integer',
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
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
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
     * 获取处理进度百分比
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_count === 0) {
            return 0;
        }
        return round(($this->processed_count / $this->total_count) * 100, 2);
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
     * 关联手机号明细
     */
    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneBatchNumber::class);
    }

    /**
     * 关联比对结果
     */
    public function comparisons(): HasMany
    {
        return $this->hasMany(BatchComparison::class);
    }

    /**
     * 关联绑定的群组
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * 获取所有手机号
     */
    public function getPhoneNumbers(): array
    {
        return $this->phoneNumbers()->pluck('phone_number')->toArray();
    }

    /**
     * 设置手机号列表并更新计数
     * 支持大批量导入，使用分批插入避免内存和超时问题
     */
    public function setPhoneNumbers(array $phoneNumbers): void
    {
        // 对于大批量导入，尝试增加执行时间限制（如果服务器允许）
        if (function_exists('set_time_limit')) {
            @set_time_limit(0); // 0 表示无限制
        }
        
        // 先删除现有的手机号
        $this->phoneNumbers()->delete();
        
        // 清理手机号，移除所有非数字字符
        $cleanNumbers = [];
        foreach ($phoneNumbers as $number) {
            $cleanNumber = $this->cleanPhoneNumber($number);
            if ($cleanNumber && strlen($cleanNumber) >= 3) { // 至少3位数字
                $cleanNumbers[] = $cleanNumber;
            }
        }
        
        // 去重
        $cleanNumbers = array_unique($cleanNumbers);
        
        // 如果没有任何有效号码，直接返回
        if (empty($cleanNumbers)) {
            $this->total_count = 0;
            $this->processed_count = 0;
            $this->status = self::STATUS_PENDING;
            $this->save();
            return;
        }
        
        // 使用事务确保数据一致性
        \DB::beginTransaction();
        
        try {
            // 分批插入，每批1000条，避免一次性插入过多数据
            // 可以根据实际情况调整批次大小
            $batchSize = 1000;
            $chunks = array_chunk($cleanNumbers, $batchSize);
            $now = now();
            
            foreach ($chunks as $index => $chunk) {
                $batchNumbers = [];
                foreach ($chunk as $phoneNumber) {
                    $batchNumbers[] = [
                        'phone_batch_id' => $this->id,
                        'phone_number' => $phoneNumber,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                // 分批插入
                \DB::table('phone_batch_numbers')->insert($batchNumbers);
                
                // 每处理10批，更新一次进度（可选，用于显示进度）
                if (($index + 1) % 10 === 0) {
                    // 可以在这里更新进度，但为了性能，暂时不更新
                    // $this->processed_count = ($index + 1) * $batchSize;
                    // $this->save();
                }
            }
            
            // 提交事务
            \DB::commit();
            
            // 更新批次统计信息
            $this->total_count = count($cleanNumbers);
            $this->processed_count = count($cleanNumbers);
            $this->status = self::STATUS_COMPLETED;
            $this->save();
            
        } catch (\Exception $e) {
            // 回滚事务
            \DB::rollBack();
            
            // 记录错误并更新状态
            \Log::error('批量导入手机号失败', [
                'phone_batch_id' => $this->id,
                'total_numbers' => count($cleanNumbers),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->total_count = count($cleanNumbers);
            $this->processed_count = 0;
            $this->status = self::STATUS_FAILED;
            $this->save();
            
            // 重新抛出异常，让调用者知道导入失败
            throw $e;
        }
    }

    /**
     * 清理手机号格式
     */
    private function cleanPhoneNumber(string $phoneNumber): ?string
    {
        // 移除所有非数字字符（包括+号、空格、横线等）
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // 如果是空字符串，返回 null
        if (empty($cleaned)) {
            return null;
        }
        
        // 直接返回清理后的纯数字
        return $cleaned;
    }


    /**
     * 从文本创建批次
     */
    public static function createFromText(string $name, string $description, string $phoneNumbersText): self
    {
        $phoneNumbers = array_filter(array_map('trim', explode("\n", $phoneNumbersText)));
        
        $batch = new self();
        $batch->name = $name;
        $batch->description = $description;
        $batch->status = self::STATUS_PENDING;
        $batch->save(); // 先保存获取ID
        
        $batch->setPhoneNumbers($phoneNumbers);
        
        return $batch;
    }

    /**
     * 检查手机号是否在批次中（高效查询）
     */
    public function hasPhoneNumber(string $phoneNumber): bool
    {
        $cleanNumber = $this->cleanPhoneNumber($phoneNumber);
        return $this->phoneNumbers()->where('phone_number', $cleanNumber)->exists();
    }

    /**
     * 获取与WhatsApp用户匹配的手机号
     */
    public function getMatchedPhoneNumbers(array $whatsappPhoneNumbers): array
    {
        $cleanWhatsappNumbers = array_map([$this, 'cleanPhoneNumber'], $whatsappPhoneNumbers);
        $cleanWhatsappNumbers = array_filter($cleanWhatsappNumbers);
        
        return $this->phoneNumbers()
            ->whereIn('phone_number', $cleanWhatsappNumbers)
            ->pluck('phone_number')
            ->toArray();
    }

    /**
     * 更新处理计数
     */
    public function updateProcessedCount(int $count): void
    {
        $this->processed_count = $count;
        
        // 如果处理完成，更新状态
        if ($count >= $this->total_count) {
            $this->status = self::STATUS_COMPLETED;
        }
        
        $this->save();
    }
}