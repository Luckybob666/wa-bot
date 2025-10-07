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
        return $this->nickname ?: $this->getFormattedPhoneNumberAttribute();
    }
    
    /**
     * 获取格式化的手机号
     */
    public function getFormattedPhoneNumberAttribute(): string
    {
        if (!$this->phone_number) return '';
        
        // 移除所有非数字字符
        $digits = preg_replace('/\D/', '', $this->phone_number);
        
        // 检查长度是否合理
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return $this->phone_number; // 返回原始值
        }
        
        // 格式化显示
        if (strlen($digits) > 10) {
            // 国际号码格式：+60 12-345 6789
            $countryCode = substr($digits, 0, -10);
            $localNumber = substr($digits, -10);
            return "+{$countryCode} " . substr($localNumber, 0, 2) . "-" . 
                   substr($localNumber, 2, 3) . " " . substr($localNumber, 5);
        } else {
            // 本地号码格式：012-345 6789
            return substr($digits, 0, 3) . "-" . substr($digits, 3, 3) . " " . 
                   substr($digits, 6);
        }
    }
    
    /**
     * 检查手机号是否异常
     */
    public function hasAbnormalPhoneNumber(): bool
    {
        if (!$this->phone_number) return false;
        
        $digits = preg_replace('/\D/', '', $this->phone_number);
        return strlen($digits) > 15 || !preg_match('/^\d+$/', $digits);
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
