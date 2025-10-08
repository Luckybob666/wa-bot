<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'jid',
        'nickname',
        'profile_picture',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取用户所属的群
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_whatsapp_user')
            ->withPivot(['joined_at', 'left_at', 'is_admin'])
            ->withTimestamps();
    }

    /**
     * 获取用户当前所在的群（未退出的群）
     */
    public function activeGroups(): BelongsToMany
    {
        return $this->groups()->wherePivotNull('left_at');
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
        return $this->nickname ?: $this->phone_number;
    }

    /**
     * 检查用户是否为群管理员
     */
    public function isAdminOf(Group $group): bool
    {
        $pivot = $this->groups()->where('group_id', $group->id)->first()?->pivot;
        return $pivot ? (bool) $pivot->is_admin : false;
    }

    /**
     * 获取用户加入群的时间
     */
    public function getJoinedAtForGroup(Group $group): ?\Carbon\Carbon
    {
        $pivot = $this->groups()->where('group_id', $group->id)->first()?->pivot;
        return $pivot ? $pivot->joined_at : null;
    }

    /**
     * 获取用户退出群的时间
     */
    public function getLeftAtForGroup(Group $group): ?\Carbon\Carbon
    {
        $pivot = $this->groups()->where('group_id', $group->id)->first()?->pivot;
        return $pivot ? $pivot->left_at : null;
    }

    /**
     * 检查用户是否在指定群中
     */
    public function isInGroup(Group $group): bool
    {
        return $this->activeGroups()->where('group_id', $group->id)->exists();
    }
}
