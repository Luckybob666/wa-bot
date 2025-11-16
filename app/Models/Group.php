<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'whatsapp_groups';

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
        'matched_count',
        'unmatched_count',
        'extra_count',
        'match_rate',
        'status',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'member_count' => 'integer',
        'matched_count' => 'integer',
        'unmatched_count' => 'integer',
        'extra_count' => 'integer',
        'match_rate' => 'decimal:2',
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
     * 状态常量
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_REMOVED = 'removed';

    /**
     * 获取群的所有用户
     */
    public function whatsappUsers(): HasMany
    {
        return $this->hasMany(WhatsappUser::class);
    }

    /**
     * 获取群当前活跃的用户（is_active = true）
     */
    public function activeUsers(): HasMany
    {
        return $this->whatsappUsers()->where('is_active', true);
    }

    /**
     * 获取群的管理员
     */
    public function admins(): HasMany
    {
        return $this->whatsappUsers()->where('is_admin', true);
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
            'matched_count' => $this->matched_count ?? 0,
            'unmatched_count' => $this->unmatched_count ?? 0,
            'extra_count' => $this->extra_count ?? 0,
            'match_rate' => $this->match_rate ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
        $this->save();
        // 刷新关系以确保能正确获取批次数据
        $this->load('phoneBatch');
        // 绑定后立即更新比对数据
        $this->updateBatchComparison();
    }

    /**
     * 解绑手机号批次
     */
    public function unbindBatch(): void
    {
        $this->phone_batch_id = null;
        $this->matched_count = 0;
        $this->unmatched_count = 0;
        $this->extra_count = 0;
        $this->match_rate = 0;
        $this->save();
    }

    /**
     * 检查群是否活跃
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 检查群是否已退出
     */
    public function isRemoved(): bool
    {
        return $this->status === self::STATUS_REMOVED;
    }

    /**
     * 作用域：筛选活跃的群
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 作用域：筛选已退出的群
     */
    public function scopeRemoved($query)
    {
        return $query->where('status', self::STATUS_REMOVED);
    }

    /**
     * 获取原有人数（首次记录群组信息时已在群内的成员数，且当前仍在群内）
     * 原有人数 = created_at 精确等于群组 created_at 且 is_active = true 的用户数
     * 首次统计时，所有用户的 created_at 都会被直接复用群组的 created_at（一毫秒都不差）
     */
    public function getOriginalMemberCount(): int
    {
        if (!$this->created_at) {
            return 0;
        }

        $groupCreatedAt = $this->created_at->format('Y-m-d H:i:s');
        
        return $this->whatsappUsers()
            ->where('is_active', true)
            ->whereRaw('created_at = ?', [$groupCreatedAt])
            ->count();
    }

    /**
     * 获取新进群人数（首次记录群组信息后首次加入的用户，无论是否已退群）
     * 新进群人数 = created_at > 群组 created_at 的用户数（不限制 is_active 状态）
     */
    public function getNewJoinedMemberCount(): int
    {
        if (!$this->created_at) {
            return 0;
        }

        return $this->whatsappUsers()
            ->where('created_at', '>', $this->created_at)
            ->count();
    }

    /**
     * 获取当前人数（活跃用户数）
     * 当前人数 = is_active = true 的用户数
     */
    public function getCurrentMemberCount(): int
    {
        return $this->whatsappUsers()
            ->where('is_active', true)
            ->count();
    }

    /**
     * 获取退出人数（主动退出）
     * 退群人数 = is_active = false 且 removed_by_admin = false 的用户数
     */
    public function getLeftMemberCount(): int
    {
        return $this->whatsappUsers()
            ->where('is_active', false)
            ->where('removed_by_admin', false)
            ->count();
    }

    /**
     * 获取被移除的用户数
     * 被管理员移除人数 = is_active = false 且 removed_by_admin = true 的用户数
     */
    public function getRemovedMemberCount(): int
    {
        return $this->whatsappUsers()
            ->where('is_active', false)
            ->where('removed_by_admin', true)
            ->count();
    }

    /**
     * 更新批次比对数据（实时计算并保存到数据库）
     */
    public function updateBatchComparison(): void
    {
        \Log::info("开始更新批次比对数据", [
            'group_id' => $this->id,
            'phone_batch_id' => $this->phone_batch_id
        ]);

        if (!$this->phone_batch_id) {
            $this->matched_count = 0;
            $this->unmatched_count = 0;
            $this->extra_count = 0;
            $this->match_rate = 0;
            $this->save();
            \Log::info("未绑定批次，重置比对数据");
            return;
        }

        // 确保关系已加载
        if (!$this->relationLoaded('phoneBatch')) {
            $this->load('phoneBatch');
        }
        
        $batch = $this->phoneBatch;
        if (!$batch) {
            \Log::warning("批次不存在", ['phone_batch_id' => $this->phone_batch_id]);
            $this->matched_count = 0;
            $this->unmatched_count = 0;
            $this->extra_count = 0;
            $this->match_rate = 0;
            $this->save();
            return;
        }

        $batchNumbers = $batch->getPhoneNumbers();
        if (empty($batchNumbers)) {
            \Log::info("批次中没有手机号");
            $this->matched_count = 0;
            $this->unmatched_count = 0;
            $this->extra_count = 0;
            $this->match_rate = 0;
            $this->save();
            return;
        }

        \Log::info("批次手机号数量", ['count' => count($batchNumbers)]);

        // 获取群内所有活跃用户的手机号（清理格式）
        $groupNumbers = $this->whatsappUsers()
            ->where('is_active', true)
            ->whereNotNull('phone_number')
            ->pluck('phone_number')
            ->map(function ($phone) {
                return preg_replace('/[^0-9]/', '', $phone);
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // 计算匹配数量
        $matched = array_intersect($batchNumbers, $groupNumbers);
        $matchedCount = count($matched);

        // 计算未匹配数量
        $unmatched = array_diff($batchNumbers, $groupNumbers);
        $unmatchedCount = count($unmatched);

        // 计算群里多出的数量
        $extra = array_diff($groupNumbers, $batchNumbers);
        $extraCount = count($extra);

        // 计算匹配率
        $matchRate = 0;
        if ($batch->total_count > 0) {
            $matchRate = round(($matchedCount / $batch->total_count) * 100, 2);
        }

        // 更新数据库字段
        $this->matched_count = $matchedCount;
        $this->unmatched_count = $unmatchedCount;
        $this->extra_count = $extraCount;
        $this->match_rate = $matchRate;
        $this->save();

        \Log::info("批次比对数据更新完成", [
            'group_id' => $this->id,
            'matched_count' => $matchedCount,
            'unmatched_count' => $unmatchedCount,
            'extra_count' => $extraCount,
            'match_rate' => $matchRate
        ]);
    }

    /**
     * 获取批次中已进群的数量（从数据库字段读取）
     */
    public function getBatchMatchedCount(): int
    {
        return $this->matched_count ?? 0;
    }

    /**
     * 获取批次中未进群的数量（从数据库字段读取）
     */
    public function getBatchUnmatchedCount(): int
    {
        return $this->unmatched_count ?? 0;
    }

    /**
     * 获取群里多出的数量（从数据库字段读取）
     */
    public function getBatchExtraCount(): int
    {
        return $this->extra_count ?? 0;
    }

    /**
     * 获取匹配率（从数据库字段读取）
     */
    public function getMatchRate(): float
    {
        return (float) ($this->match_rate ?? 0);
    }

    /**
     * 导出用户明细到CSV
     * 
     * @return string CSV内容
     */
    public function exportUsersToCsv(): string
    {
        // 获取批次手机号（如果绑定了批次）
        $batchNumbers = [];
        if ($this->phone_batch_id && $this->phoneBatch) {
            $batchNumbers = $this->phoneBatch->getPhoneNumbers();
            $batchNumbers = array_map(function($num) {
                return preg_replace('/[^0-9]/', '', $num);
            }, $batchNumbers);
        }

        // 获取所有用户及其关系（包括已退出的）
        $users = $this->whatsappUsers()->get();
        
        // 获取群内所有用户的手机号（清理格式，用于比对）
        // 包括活跃用户和已退出的用户（只要曾经进过群就记录）
        $groupAllPhones = [];
        foreach ($users as $user) {
            if ($user->phone_number) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $user->phone_number);
                if (!empty($cleanPhone)) {
                    $groupAllPhones[$cleanPhone] = $user;
                }
            }
        }

        // 如果绑定了批次，找出批次中从未进过群的号码
        // 只包含那些在批次中但从未在群内用户列表中出现过的号码
        $unmatchedBatchNumbers = [];
        if (!empty($batchNumbers)) {
            foreach ($batchNumbers as $batchPhone) {
                // 如果这个号码从未在群内用户列表中出现过，才添加到未进群列表
                if (!isset($groupAllPhones[$batchPhone])) {
                    $unmatchedBatchNumbers[] = $batchPhone;
                }
            }
        }
        
        // 准备CSV头部
        $headers = [
            '手机号',
            'WhatsApp用户ID',
            'JID',
            '昵称',
            '创建时间',
            '退出时间',
            '是否管理员',
            '是否被移除',
            // 基础分类标识列
            '是否原有人',
            '是否新进群',
            '是否已退出',
            '是否被移除（标识）',
            '用户状态',
        ];

        // 如果绑定了批次，添加批次比对标识列
        if (!empty($batchNumbers)) {
            $headers = array_merge($headers, [
                '是否已进群',
                '是否未进群',
                '是否群里多出',
                '比对状态',
            ]);
        }

        // 创建CSV内容
        $csvLines = [];
        
        // 添加BOM以支持Excel中文显示
        $csvLines[] = "\xEF\xBB\xBF" . implode(',', array_map(function($header) {
            return '"' . str_replace('"', '""', $header) . '"';
        }, $headers));

        // 处理每个用户（群内用户）
        foreach ($users as $user) {
            // 清理手机号格式用于比对
            $phoneNumber = $user->phone_number ?? '';
            $cleanPhone = $phoneNumber ? preg_replace('/[^0-9]/', '', $phoneNumber) : '';
            
            // 判断基础分类
            $isOriginal = false;
            $isNewJoined = false;
            $isLeft = !$user->is_active;
            $isRemoved = $user->removed_by_admin ?? false;

            if ($this->created_at) {
                // 使用 created_at 来判断：created_at = 群组 created_at 为原有人，created_at > 群组 created_at 为新进群
                $userCreatedAt = $user->created_at ? \Carbon\Carbon::parse($user->created_at) : null;
                $groupCreatedAt = \Carbon\Carbon::parse($this->created_at);
                
                if ($userCreatedAt && $userCreatedAt->eq($groupCreatedAt)) {
                    $isOriginal = true;
                } elseif ($userCreatedAt && $userCreatedAt->gt($groupCreatedAt)) {
                    $isNewJoined = true;
                }
            } else {
                // 如果没有群组 created_at，所有用户都视为原有人
                $isOriginal = true;
            }

            // 确定用户状态
            $userStatus = '原有人';
            if ($isNewJoined) {
                $userStatus = '新进群';
            }
            if ($isLeft && !$isRemoved) {
                $userStatus = '已退出';
            }
            if ($isRemoved) {
                $userStatus = '被移除';
            }

            // 构建行数据
            $row = [
                $phoneNumber,
                $user->whatsapp_user_id ?? '',
                $user->jid ?? '',
                $user->nickname ?? '',
                $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('Y-m-d H:i:s') : '',
                $user->left_at ? \Carbon\Carbon::parse($user->left_at)->format('Y-m-d H:i:s') : '',
                $user->is_admin ? '是' : '否',
                $isRemoved ? '是' : '否',
                // 基础分类标识
                $isOriginal ? '是' : '否',
                $isNewJoined ? '是' : '否',
                $isLeft && !$isRemoved ? '是' : '否',
                $isRemoved ? '是' : '否',
                $userStatus,
            ];

            // 如果绑定了批次，添加批次比对标识
            if (!empty($batchNumbers)) {
                $isInBatch = !empty($cleanPhone) && in_array($cleanPhone, $batchNumbers);
                $isMatched = $isInBatch && $user->is_active; // 已进群：在批次中且当前活跃
                $isUnmatched = $isInBatch && !$user->is_active; // 未进群：在批次中但已退出
                $isExtra = !empty($cleanPhone) && !in_array($cleanPhone, $batchNumbers) && $user->is_active; // 群里多出：不在批次中且当前活跃

                $compareStatus = '不在批次中';
                if ($isMatched) {
                    $compareStatus = '已进群';
                } elseif ($isUnmatched) {
                    $compareStatus = '未进群';
                } elseif ($isExtra) {
                    $compareStatus = '群里多出';
                }

                $row = array_merge($row, [
                    $isMatched ? '是' : '否',
                    $isUnmatched ? '是' : '否',
                    $isExtra ? '是' : '否',
                    $compareStatus,
                ]);
            }

            // 转义CSV字段
            $csvLines[] = implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', (string)$field) . '"';
            }, $row));
        }

        // 如果绑定了批次，添加批次中未进群的号码（这些号码不在群内用户列表中）
        if (!empty($batchNumbers) && !empty($unmatchedBatchNumbers)) {
            foreach ($unmatchedBatchNumbers as $unmatchedPhone) {
                // 构建未进群号码的行数据
                $row = [
                    $unmatchedPhone, // 手机号
                    '', // WhatsApp用户ID（未进群，没有记录）
                    '', // JID（未进群，没有记录）
                    '', // 昵称（未进群，没有记录）
                    '', // 创建时间（未进群，没有记录）
                    '', // 退出时间（未进群，没有记录）
                    '否', // 是否管理员（未进群）
                    '否', // 是否被移除（未进群）
                    // 基础分类标识
                    '否', // 是否原有人
                    '否', // 是否新进群
                    '否', // 是否已退出
                    '否', // 是否被移除（标识）
                    '未进群', // 用户状态
                ];

                // 批次比对标识
                $row = array_merge($row, [
                    '否', // 是否已进群
                    '是', // 是否未进群
                    '否', // 是否群里多出
                    '未进群', // 比对状态
                ]);

                // 转义CSV字段
                $csvLines[] = implode(',', array_map(function($field) {
                    return '"' . str_replace('"', '""', (string)$field) . '"';
                }, $row));
            }
        }

        return implode("\n", $csvLines);
    }
}
