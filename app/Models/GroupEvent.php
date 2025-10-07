<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupEvent extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'bot_id',
        'group_id',
        'whatsapp_user_id',
        'event_type',
        'event_data',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * 事件类型常量
     */
    const EVENT_MEMBER_JOINED = 'member_joined';
    const EVENT_MEMBER_LEFT = 'member_left';
    const EVENT_GROUP_UPDATED = 'group_updated';

    /**
     * 获取所有事件类型选项
     */
    public static function getEventTypeOptions(): array
    {
        return [
            self::EVENT_MEMBER_JOINED => '成员加入',
            self::EVENT_MEMBER_LEFT => '成员退出',
            self::EVENT_GROUP_UPDATED => '群信息更新',
        ];
    }

    /**
     * 获取事件类型标签
     */
    public function getEventTypeLabelAttribute(): string
    {
        return self::getEventTypeOptions()[$this->event_type] ?? '未知事件';
    }

    /**
     * 获取事件所属的机器人
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * 获取事件所属的群
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * 获取事件相关的用户
     */
    public function whatsappUser(): BelongsTo
    {
        return $this->belongsTo(WhatsappUser::class);
    }

    /**
     * 获取事件描述
     */
    public function getDescriptionAttribute(): string
    {
        $groupName = $this->group->name ?? "群 {$this->group_id}";
        $userName = $this->whatsappUser->display_name ?? '未知用户';

        return match ($this->event_type) {
            self::EVENT_MEMBER_JOINED => "用户 {$userName} 加入了群 {$groupName}",
            self::EVENT_MEMBER_LEFT => "用户 {$userName} 退出了群 {$groupName}",
            self::EVENT_GROUP_UPDATED => "群 {$groupName} 的信息已更新",
            default => "群 {$groupName} 发生了未知事件",
        };
    }

    /**
     * 获取事件图标
     */
    public function getIconAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_MEMBER_JOINED => 'heroicon-o-user-plus',
            self::EVENT_MEMBER_LEFT => 'heroicon-o-user-minus',
            self::EVENT_GROUP_UPDATED => 'heroicon-o-pencil-square',
            default => 'heroicon-o-information-circle',
        };
    }

    /**
     * 获取事件颜色
     */
    public function getColorAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_MEMBER_JOINED => 'success',
            self::EVENT_MEMBER_LEFT => 'danger',
            self::EVENT_GROUP_UPDATED => 'warning',
            default => 'gray',
        };
    }

    /**
     * 获取事件时间（人类可读格式）
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * 获取事件详细信息
     */
    public function getDetailsAttribute(): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'event_type_label' => $this->event_type_label,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'bot_name' => $this->bot->name ?? '未知机器人',
            'group_name' => $this->group->name ?? "群 {$this->group_id}",
            'user_name' => $this->whatsappUser->display_name ?? null,
            'user_phone' => $this->whatsappUser->phone_number ?? null,
            'event_data' => $this->event_data,
            'created_at' => $this->created_at,
            'time_ago' => $this->time_ago,
        ];
    }

    /**
     * 查询成员加入事件
     */
    public function scopeMemberJoined($query)
    {
        return $query->where('event_type', self::EVENT_MEMBER_JOINED);
    }

    /**
     * 查询成员退出事件
     */
    public function scopeMemberLeft($query)
    {
        return $query->where('event_type', self::EVENT_MEMBER_LEFT);
    }

    /**
     * 查询群更新事件
     */
    public function scopeGroupUpdated($query)
    {
        return $query->where('event_type', self::EVENT_GROUP_UPDATED);
    }

    /**
     * 查询指定机器人的事件
     */
    public function scopeForBot($query, int $botId)
    {
        return $query->where('bot_id', $botId);
    }

    /**
     * 查询指定群的事件
     */
    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * 查询指定用户的事件
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('whatsapp_user_id', $userId);
    }

    /**
     * 查询最近的事件
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
