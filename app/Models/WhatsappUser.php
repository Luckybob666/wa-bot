<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WhatsappUser extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'phone_number',
        'whatsapp_user_id',
        'lid',
        'jid',
        'nickname',
        'profile_picture',
        'group_id',
        'bot_id',
        'left_at',
        'is_active',
        'is_admin',
        'removed_by_admin',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'left_at' => 'datetime',
        'is_active' => 'boolean',
        'is_admin' => 'boolean',
        'removed_by_admin' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取用户所属的机器人
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * 获取用户所属的群
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * 获取用户参与的群事件
     */
    public function groupEvents(): HasMany
    {
        return $this->hasMany(GroupEvent::class);
    }

    /**
     * 获取用户头像URL
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->profile_picture ?: 'https://via.placeholder.com/150?text=' . urlencode($this->nickname ?: 'U');
    }

    /**
     * 获取用户显示名称
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?: $this->phone_number ?: $this->whatsapp_user_id ?: '未设置昵称';
    }

    /**
     * 检查用户是否为群管理员
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * 检查用户是否在群内（活跃状态）
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
