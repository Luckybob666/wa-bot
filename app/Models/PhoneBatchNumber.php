<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneBatchNumber extends Model
{
    protected $fillable = [
        'phone_batch_id',
        'phone_number',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联批次
     */
    public function phoneBatch(): BelongsTo
    {
        return $this->belongsTo(PhoneBatch::class);
    }

    /**
     * 清理手机号格式
     */
    public static function cleanPhoneNumber(string $phoneNumber): ?string
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
}
