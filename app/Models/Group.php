<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'bot_id',
        'group_id',
        'name',
        'description',
        'member_count',
        'phone_batch_id',
        'auto_compare_enabled',
        'last_sync_at',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'member_count' => 'integer',
        'auto_compare_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取群所属的机器人
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * 获取群的所有用户
     */
    public function whatsappUsers(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappUser::class, 'group_whatsapp_user')
            ->withPivot(['joined_at', 'left_at', 'is_admin'])
            ->withTimestamps();
    }

    /**
     * 获取群当前活跃的用户（未退出的用户）
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->whatsappUsers()->wherePivotNull('left_at');
    }

    /**
     * 获取群的管理员
     */
    public function admins(): BelongsToMany
    {
        return $this->whatsappUsers()->wherePivot('is_admin', true);
    }

    /**
     * 获取群的事件记录
     */
    public function events(): HasMany
    {
        return $this->hasMany(GroupEvent::class);
    }

    /**
     * 获取群的比对记录
     */
    public function comparisons(): HasMany
    {
        return $this->hasMany(BatchComparison::class);
    }

    /**
     * 获取绑定的手机号批次
     */
    public function phoneBatch(): BelongsTo
    {
        return $this->belongsTo(PhoneBatch::class);
    }

    /**
     * 获取群的最新事件
     */
    public function latestEvents(int $limit = 10)
    {
        return $this->hasMany(GroupEvent::class)->latest()->limit($limit);
    }

    /**
     * 获取群成员数量（实时统计）
     */
    public function getActiveMemberCountAttribute(): int
    {
        return $this->activeUsers()->count();
    }

    /**
     * 获取群的总成员数量（包括已退出的）
     */
    public function getTotalMemberCountAttribute(): int
    {
        return $this->whatsappUsers()->count();
    }

    /**
     * 获取群的管理员数量
     */
    public function getAdminCountAttribute(): int
    {
        return $this->admins()->count();
    }

    /**
     * 检查用户是否为群管理员
     */
    public function hasAdmin(WhatsappUser $user): bool
    {
        return $this->admins()->where('whatsapp_user_id', $user->id)->exists();
    }

    /**
     * 检查用户是否在群中
     */
    public function hasUser(WhatsappUser $user): bool
    {
        return $this->activeUsers()->where('whatsapp_user_id', $user->id)->exists();
    }

    /**
     * 获取群显示名称
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: "群 {$this->group_id}";
    }

    /**
     * 获取群完整信息
     */
    public function getFullInfoAttribute(): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'name' => $this->name,
            'description' => $this->description,
            'bot_name' => $this->bot->name ?? '未知机器人',
            'active_member_count' => $this->active_member_count,
            'total_member_count' => $this->total_member_count,
            'admin_count' => $this->admin_count,
            'phone_batch_name' => $this->phoneBatch->name ?? null,
            'auto_compare_enabled' => $this->auto_compare_enabled,
            'last_sync_at' => $this->last_sync_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * 检查是否绑定了批次
     */
    public function hasBatchBinding(): bool
    {
        return !is_null($this->phone_batch_id);
    }

    /**
     * 绑定手机号批次
     */
    public function bindBatch(PhoneBatch $batch): void
    {
        $this->phone_batch_id = $batch->id;
        $this->auto_compare_enabled = true;
        $this->save();
    }

    /**
     * 解绑手机号批次
     */
    public function unbindBatch(): void
    {
        $this->phone_batch_id = null;
        $this->auto_compare_enabled = false;
        $this->save();
    }

    /**
     * 更新最后同步时间
     */
    public function updateLastSyncTime(): void
    {
        $this->last_sync_at = now();
        $this->save();
    }
}
