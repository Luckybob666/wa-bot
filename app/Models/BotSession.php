<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BotSession extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'bot_id',
        'session_data',
        'qr_code',
        'expires_at',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'session_data' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取会话所属的机器人
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * 检查会话是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 检查会话是否有效
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !empty($this->session_data);
    }

    /**
     * 获取QR码数据（Base64格式）
     */
    public function getQrCodeDataAttribute(): ?string
    {
        return $this->qr_code;
    }

    /**
     * 获取QR码图片URL
     */
    public function getQrCodeUrlAttribute(): ?string
    {
        if (!$this->qr_code) {
            return null;
        }

        return 'data:image/png;base64,' . $this->qr_code;
    }

    /**
     * 获取会话状态
     */
    public function getStatusAttribute(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isValid()) {
            return 'valid';
        }

        return 'invalid';
    }

    /**
     * 获取会话状态标签
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'valid' => '有效',
            'expired' => '已过期',
            'invalid' => '无效',
            default => '未知',
        };
    }

    /**
     * 获取会话剩余时间（秒）
     */
    public function getRemainingTimeAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        $remaining = $this->expires_at->diffInSeconds(now(), false);
        return $remaining > 0 ? $remaining : 0;
    }

    /**
     * 获取会话剩余时间（人类可读格式）
     */
    public function getRemainingTimeHumanAttribute(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }

        return $this->expires_at->diffForHumans(now(), true);
    }

    /**
     * 清理过期的会话
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * 获取机器人的最新有效会话
     */
    public static function getLatestValidForBot(int $botId): ?self
    {
        return static::where('bot_id', $botId)
            ->where('expires_at', '>', now())
            ->whereNotNull('session_data')
            ->latest()
            ->first();
    }
}
