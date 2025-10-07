<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Http;

class Bot extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'name',
        'phone_number',
        'status',
        'session_data',
        'last_seen',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'session_data' => 'array',
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 状态常量
     */
    const STATUS_OFFLINE = 'offline';
    const STATUS_CONNECTING = 'connecting';
    const STATUS_ONLINE = 'online';
    const STATUS_ERROR = 'error';

    /**
     * 获取所有状态选项
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_OFFLINE => '离线',
            self::STATUS_CONNECTING => '连接中',
            self::STATUS_ONLINE => '在线',
            self::STATUS_ERROR => '错误',
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
     * 检查机器人是否在线
     */
    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ONLINE;
    }

    /**
     * 检查机器人是否离线
     */
    public function isOffline(): bool
    {
        return $this->status === self::STATUS_OFFLINE;
    }

    /**
     * 获取机器人管理的群
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * 获取机器人的会话记录
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(BotSession::class);
    }

    /**
     * 获取机器人的群事件
     */
    public function groupEvents(): HasMany
    {
        return $this->hasMany(GroupEvent::class);
    }

    /**
     * 获取最新的会话
     */
    public function latestSession()
    {
        return $this->hasOne(BotSession::class)->latest();
    }

    /**
     * 模型删除前的钩子
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($bot) {
            // 通知 Node.js 服务器停止机器人
            try {
                $nodeUrl = config('app.node_server.url');
                $timeout = config('app.node_server.timeout', 10);
                
                Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $bot->id . '/stop');
                
                \Log::info("已通知 Node.js 停止机器人 #{$bot->id}");
            } catch (\Exception $e) {
                \Log::error("通知 Node.js 停止机器人失败: " . $e->getMessage());
            }
        });
    }
}
