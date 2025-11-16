<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Group;
use App\Models\GroupEvent;
use App\Models\WhatsappUser;
use App\Services\GroupEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotController extends Controller
{
    /**
     * 查找机器人或返回404响应
     */
    protected function findBotOrResponse(int|string $id): array
    {
        $bot = Bot::find($id);
        if (!$bot) {
            return [null, response()->json(['success' => false, 'message' => "No query results for model [App\\Models\\Bot] {$id}"], 404)];
        }
        return [$bot, null];
    }

    /**
     * 机器人加入群组
     */
    public function groupJoined(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $groupId = $request->input('groupId');
            $groupName = $request->input('name');
            $memberCount = $request->input('memberCount', 0);

            if (empty($groupId)) {
                return response()->json(['success' => false, 'message' => '群组ID不能为空'], 400);
            }

            $group = Group::where('bot_id', $bot->id)->where('group_id', $groupId)->first();
            $isRejoin = $group !== null;
            $isActuallyRejoin = false;

            if ($group) {
                $oldStatus = $group->status;
                
                if ($oldStatus === Group::STATUS_REMOVED) {
                    $isActuallyRejoin = true;
                    $group->update([
                        'name' => $groupName ?: $group->name,
                        'status' => Group::STATUS_ACTIVE,
                        'member_count' => $memberCount,
                    ]);
                } else {
                    $group->update([
                        'name' => $groupName ?: $group->name,
                        'member_count' => $memberCount,
                    ]);
                }
                // 刷新以获取最新的 updated_at
                $group->refresh();
            } else {
                // 创建新群组
                $group = Group::create([
                    'bot_id' => $bot->id,
                    'group_id' => $groupId,
                    'name' => $groupName ?: $groupId,
                    'status' => Group::STATUS_ACTIVE,
                    'member_count' => $memberCount,
                ]);
                // 刷新以获取数据库生成的 created_at（确保时间戳精确）
                $group->refresh();
            }

            if (!$isRejoin || $isActuallyRejoin) {
                GroupEventService::recordBotJoinedGroup($bot->id, $group->id, $groupName ?: $groupId, $memberCount, $isActuallyRejoin);
            }

            return response()->json([
                'success' => true,
                'message' => $isRejoin ? '群组状态已更新（重新加入）' : '群组创建成功',
                'data' => ['group_id' => $group->id, 'group_id_whatsapp' => $groupId, 'is_rejoin' => $isRejoin]
            ]);
        } catch (\Exception $e) {
            Log::error("处理机器人加入群组失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 机器人退出群组
     */
    public function groupLeft(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $groupId = $request->input('groupId');
            if (empty($groupId)) {
                return response()->json(['success' => false, 'message' => '群组ID不能为空'], 400);
            }

            $group = Group::where('bot_id', $bot->id)->where('group_id', $groupId)->first();
            if (!$group) {
                return response()->json(['success' => false, 'message' => '群组不存在'], 404);
            }

            $group->update(['status' => Group::STATUS_REMOVED]);
            GroupEventService::recordBotLeftGroup($bot->id, $group->id, $group->name);

            return response()->json(['success' => true, 'message' => '群组状态已更新为已退出', 'data' => ['group_id' => $group->id]]);
        } catch (\Exception $e) {
            Log::error("处理机器人退出群组失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 同步群组用户
     */
    public function syncGroupUser(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $group = Group::where('bot_id', $bot->id)->where('group_id', $request->input('groupId'))->first();
            if (!$group) {
                return response()->json(['success' => false, 'message' => '群组不存在'], 404);
            }

            $phoneNumber = $request->input('phoneNumber');
            $whatsappUserId = $request->input('whatsappUserId'); // participants 中的 id 字段
            $lid = $request->input('lid'); // participants 中的 lid 字段
            $jid = $request->input('jid');
            $groupId = $request->input('groupId');

            // 检查用户是否已存在（优先通过 whatsapp_user_id 查找，因为它是唯一标识）
            $existingUser = WhatsappUser::where('whatsapp_user_id', $whatsappUserId)
                ->where('group_id', $group->id)
                ->first();

            $shouldRecordJoin = false;

            if ($existingUser) {
                // 用户已存在
                if ($existingUser->is_active) {
                    // 用户已在群内且状态为 true，什么都不做
                    return response()->json([
                        'success' => true,
                        'message' => '用户已在群内，无需更新',
                        'data' => ['group_id' => $group->id, 'whatsapp_user_id' => $existingUser->id]
                    ]);
                } else {
                    // 用户已存在但状态为 false（之前退群或被移除），现在重新进群
                    $shouldRecordJoin = true; // 记录重新加入事件
                    
                    // 更新状态为 true，清空 left_at
                    $updateData = [
                        'is_active' => true,
                        'left_at' => null,
                        'is_admin' => $request->input('isAdmin', false),
                        'removed_by_admin' => false,
                    ];
                    
                    // 只更新非空字段
                    if ($lid) {
                        $updateData['lid'] = $lid;
                    }
                    if ($jid) {
                        $updateData['jid'] = $jid;
                    }
                    if ($phoneNumber !== null) {
                        $updateData['phone_number'] = $phoneNumber;
                    }
                    
                    $existingUser->update($updateData);
                }
            } else {
                // 用户不存在，创建新记录
                // 判断是否是首次统计群组：检查群组创建时间与当前时间的差值
                // 如果群组刚创建（比如在30秒内），认为是首次统计，所有用户都复用群组的 created_at
                $now = now();
                $group->refresh(); // 确保获取数据库中的 created_at
                $groupCreatedAt = $group->created_at;
                $timeDiff = abs($now->diffInSeconds($groupCreatedAt));
                
                // 如果群组创建时间与当前时间相差在30秒内，认为是首次统计
                // 首次统计时，所有用户的 created_at 都复用群组的 created_at（一毫秒都不差）
                if ($timeDiff <= 30) {
                    $userCreatedAt = $groupCreatedAt;
                } else {
                    // 新进群：使用当前时间
                    $userCreatedAt = $now;
                }
                $shouldRecordJoin = true; // 记录加入事件
                
                $whatsappUser = WhatsappUser::create([
                    'phone_number' => $phoneNumber,
                    'whatsapp_user_id' => $whatsappUserId, // participants 中的 id 字段
                    'lid' => $lid, // participants 中的 lid 字段（必须存储）
                    'jid' => $jid,
                    'group_id' => $group->id,
                    'bot_id' => $group->bot_id,
                    'left_at' => null,
                    'is_active' => true,
                    'is_admin' => $request->input('isAdmin', false),
                    'removed_by_admin' => false,
                    'created_at' => $userCreatedAt,
                    'updated_at' => $now,
                ]);
            }

            if ($shouldRecordJoin) {
                $userForEvent = $existingUser ?? $whatsappUser;
                GroupEventService::recordMemberJoined($bot->id, $group->id, $userForEvent, $phoneNumber, $jid);
            }

            // 更新比对数据（如果绑定了批次）
            if ($group->hasBatchBinding()) {
                $group->updateBatchComparison();
            }

            $userForResponse = $existingUser ?? $whatsappUser;
            return response()->json([
                'success' => true,
                'message' => '用户完整信息同步成功',
                'data' => ['group_id' => $group->id, 'whatsapp_user_id' => $userForResponse->id]
            ]);
        } catch (\Exception $e) {
            Log::error("同步用户完整信息失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 批量同步群组用户
     */
    public function syncGroupUsers(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $groupId = $request->input('groupId');
            $members = $request->input('members', []); // 用户列表

            if (empty($groupId) || !is_array($members) || empty($members)) {
                return response()->json(['success' => false, 'message' => '群组ID和用户列表不能为空'], 400);
            }

            $group = Group::where('bot_id', $bot->id)->where('group_id', $groupId)->first();
            if (!$group) {
                return response()->json(['success' => false, 'message' => '群组不存在'], 404);
            }

            // 刷新群组以获取数据库中的 created_at
            $group->refresh();
            $groupCreatedAt = $group->created_at;

            // 判断是否为首次统计：检查该群组是否已有用户记录
            $existingUserCount = WhatsappUser::where('group_id', $group->id)->count();
            $isFirstSync = $existingUserCount === 0;

            $now = now();
            $userCreatedAt = $isFirstSync ? $groupCreatedAt : $now; // 首次统计复用群组的 created_at

            // 获取该群组所有现有用户的映射（以 whatsapp_user_id 为键）
            $existingUsers = WhatsappUser::where('group_id', $group->id)
                ->whereIn('whatsapp_user_id', array_column($members, 'whatsappUserId'))
                ->get()
                ->keyBy('whatsapp_user_id');

            $toInsert = [];
            $toUpdate = [];
            $joinedUserIds = [];

            foreach ($members as $member) {
                $whatsappUserId = $member['whatsappUserId'] ?? null;
                $lid = $member['lid'] ?? null; // participants 中的 lid 字段
                $phoneNumber = $member['phoneNumber'] ?? null;
                $jid = $member['jid'] ?? null;
                $isAdmin = $member['isAdmin'] ?? false;

                if (!$whatsappUserId) {
                    continue;
                }

                $existingUser = $existingUsers->get($whatsappUserId);

                if ($existingUser) {
                    // 用户已存在
                    if (!$existingUser->is_active) {
                        // 用户之前退群或被移除，现在重新进群
                        $updateData = [
                            'is_active' => true,
                            'left_at' => null,
                            'is_admin' => $isAdmin,
                            'removed_by_admin' => false,
                            'updated_at' => $now,
                        ];
                        
                        // 只更新非空字段
                        if ($lid) {
                            $updateData['lid'] = $lid;
                        }
                        if ($jid) {
                            $updateData['jid'] = $jid;
                        }
                        if ($phoneNumber !== null) {
                            $updateData['phone_number'] = $phoneNumber;
                        }
                        
                        $toUpdate[] = [
                            'id' => $existingUser->id,
                            'data' => $updateData
                        ];
                        $joinedUserIds[] = $existingUser->id;
                    }
                } else {
                    // 用户不存在，准备批量插入
                    $toInsert[] = [
                        'phone_number' => $phoneNumber,
                        'whatsapp_user_id' => $whatsappUserId, // participants 中的 id 字段
                        'lid' => $lid, // participants 中的 lid 字段（必须存储）
                        'jid' => $jid,
                        'group_id' => $group->id,
                        'bot_id' => $group->bot_id,
                        'left_at' => null,
                        'is_active' => true,
                        'is_admin' => $isAdmin,
                        'removed_by_admin' => false,
                        'created_at' => $userCreatedAt,
                        'updated_at' => $now,
                    ];
                }
            }

            // 批量插入新用户
            if (!empty($toInsert)) {
                // 使用 DB::table()->insert() 批量插入，然后查询获取ID
                DB::table('whatsapp_users')->insert($toInsert);
                
                // 获取刚插入的用户ID（用于记录事件）
                $whatsappUserIds = array_column($toInsert, 'whatsapp_user_id');
                $newUserIds = WhatsappUser::where('group_id', $group->id)
                    ->whereIn('whatsapp_user_id', $whatsappUserIds)
                    ->pluck('id')
                    ->toArray();
                $joinedUserIds = array_merge($joinedUserIds, $newUserIds);
            }

            // 批量更新已存在用户
            foreach ($toUpdate as $update) {
                WhatsappUser::where('id', $update['id'])->update($update['data']);
            }

            // 记录加入事件（只记录新加入的用户）
            foreach ($joinedUserIds as $userId) {
                $user = WhatsappUser::find($userId);
                if ($user) {
                    GroupEventService::recordMemberJoined(
                        $bot->id,
                        $group->id,
                        $user,
                        $user->phone_number,
                        $user->jid
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => "批量同步成功：新增 " . count($toInsert) . " 个，更新 " . count($toUpdate) . " 个",
                'data' => [
                    'group_id' => $group->id,
                    'inserted_count' => count($toInsert),
                    'updated_count' => count($toUpdate),
                    'total_count' => count($members)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("批量同步用户失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 用户被管理员移除
     */
    public function memberRemoved(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $groupId = $request->input('groupId');
            $phoneNumber = $request->input('phoneNumber');
            $whatsappUserId = $request->input('whatsappUserId');
            $jid = $request->input('jid');

            if (empty($groupId)) {
                return response()->json(['success' => false, 'message' => '群组ID不能为空'], 400);
            }

            $group = Group::where('bot_id', $bot->id)->where('group_id', $groupId)->first();
            if (!$group) {
                return response()->json(['success' => false, 'message' => '群组不存在'], 404);
            }

            // 查找用户记录（用户被移除时，应该已经存在于数据库中）
            // 优先通过 whatsapp_user_id（participants 中的 id）查找，如果找不到再通过 lid 查找
            $whatsappUser = null;
            $lid = $request->input('lid');
            
            if ($whatsappUserId) {
                $whatsappUser = WhatsappUser::where('group_id', $group->id)
                    ->where('whatsapp_user_id', $whatsappUserId)
                    ->first();
            }
            
            // 如果通过 whatsapp_user_id 找不到，再通过 lid 查找
            if (!$whatsappUser && $lid) {
                $whatsappUser = WhatsappUser::where('group_id', $group->id)
                    ->where('lid', $lid)
                    ->first();
            }
            
            // 如果还是找不到，尝试通过 jid 查找（兼容旧数据）
            if (!$whatsappUser && $jid) {
                $whatsappUser = WhatsappUser::where('group_id', $group->id)
                    ->where('jid', $jid)
                    ->first();
            }

            $now = now();
            if ($whatsappUser) {
                // 用户已存在，更新状态
                $updateData = [
                    'is_active' => false,
                    'left_at' => $now,
                    'removed_by_admin' => true,
                ];
                
                // 只更新非空字段（注意：不要更新 jid 为 lid 的值，保持原有的 jid）
                if ($lid) {
                    $updateData['lid'] = $lid;
                }
                // 不更新 jid，因为用户被移除时 jid 可能不正确（LID 用户）
                if ($phoneNumber !== null) {
                    $updateData['phone_number'] = $phoneNumber;
                }
                
                $whatsappUser->update($updateData);
            } else {
                // 用户不存在，不应该创建新记录（因为用户已经被移除，说明之前一定在群组中）
                // 可能是用户信息获取不正确，记录日志并返回错误
                Log::warning("用户被移除时找不到用户记录", [
                    'bot_id' => $bot->id,
                    'group_id' => $group->id,
                    'whatsapp_user_id' => $whatsappUserId,
                    'lid' => $lid,
                    'phone_number' => $phoneNumber,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => '用户记录不存在，无法更新移除状态',
                    'data' => ['group_id' => $group->id, 'whatsapp_user_id' => $whatsappUserId]
                ], 404);
            }

            GroupEventService::recordMemberRemoved($bot->id, $group->id, $whatsappUser, $phoneNumber, $jid);

            return response()->json([
                'success' => true,
                'message' => '用户移除状态已更新',
                'data' => ['group_id' => $group->id, 'whatsapp_user_id' => $whatsappUser->id]
            ]);
        } catch (\Exception $e) {
            Log::error("处理用户被移除失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 用户主动退出
     */
    public function memberLeft(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $groupId = $request->input('groupId');
            $phoneNumber = $request->input('phoneNumber');
            $whatsappUserId = $request->input('whatsappUserId');
            $jid = $request->input('jid');

            if (empty($groupId)) {
                return response()->json(['success' => false, 'message' => '群组ID不能为空'], 400);
            }

            $group = Group::where('bot_id', $bot->id)->where('group_id', $groupId)->first();
            if (!$group) {
                return response()->json(['success' => false, 'message' => '群组不存在'], 404);
            }

            // 查找用户记录（用户退出时，应该已经存在于数据库中）
            // 优先通过 whatsapp_user_id（participants 中的 id）查找，如果找不到再通过 lid 查找
            $whatsappUser = null;
            $lid = $request->input('lid');
            
            if ($whatsappUserId) {
                $whatsappUser = WhatsappUser::where('group_id', $group->id)
                    ->where('whatsapp_user_id', $whatsappUserId)
                    ->first();
            }
            
            // 如果通过 whatsapp_user_id 找不到，再通过 lid 查找
            if (!$whatsappUser && $lid) {
                $whatsappUser = WhatsappUser::where('group_id', $group->id)
                    ->where('lid', $lid)
                    ->first();
            }
            
            // 如果还是找不到，尝试通过 jid 查找（兼容旧数据）
            if (!$whatsappUser && $jid) {
                $whatsappUser = WhatsappUser::where('group_id', $group->id)
                    ->where('jid', $jid)
                    ->first();
            }

            $now = now();
            if ($whatsappUser) {
                // 用户已存在，更新状态
                $updateData = [
                    'is_active' => false,
                    'left_at' => $now,
                    'removed_by_admin' => false, // 清除被移除标记
                ];
                
                // 只更新非空字段（注意：不要更新 jid 为 lid 的值，保持原有的 jid）
                if ($lid) {
                    $updateData['lid'] = $lid;
                }
                // 不更新 jid，因为用户退出时 jid 可能不正确（LID 用户）
                if ($phoneNumber !== null) {
                    $updateData['phone_number'] = $phoneNumber;
                }
                
                $whatsappUser->update($updateData);
                
                // 更新比对数据（如果绑定了批次）
                if ($group->hasBatchBinding()) {
                    $group->updateBatchComparison();
                }
            } else {
                // 用户不存在，不应该创建新记录（因为用户已经退出，说明之前一定在群组中）
                // 可能是用户信息获取不正确，记录日志并返回错误
                Log::warning("用户退出时找不到用户记录", [
                    'bot_id' => $bot->id,
                    'group_id' => $group->id,
                    'whatsapp_user_id' => $whatsappUserId,
                    'lid' => $lid,
                    'phone_number' => $phoneNumber,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => '用户记录不存在，无法更新退出状态',
                    'data' => ['group_id' => $group->id, 'whatsapp_user_id' => $whatsappUserId]
                ], 404);
            }

            GroupEventService::recordMemberLeft($bot->id, $group->id, $whatsappUser, $phoneNumber, $jid);

            return response()->json([
                'success' => true,
                'message' => '用户退出状态已更新',
                'data' => ['group_id' => $group->id, 'whatsapp_user_id' => $whatsappUser->id]
            ]);
        } catch (\Exception $e) {
            Log::error("处理用户退出失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 检查并更新被移除的群组状态
     */
    public function checkRemovedGroups(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $currentGroupIds = $request->input('groupIds', []);
            if (!is_array($currentGroupIds)) {
                return response()->json(['success' => false, 'message' => 'groupIds 必须是数组'], 400);
            }

            $activeGroups = Group::where('bot_id', $bot->id)->where('status', Group::STATUS_ACTIVE)->get();
            $removedCount = 0;
            $removedGroupIds = [];

            foreach ($activeGroups as $group) {
                if (!in_array($group->group_id, $currentGroupIds)) {
                    $group->update(['status' => Group::STATUS_REMOVED]);
                    GroupEventService::recordBotLeftGroup($bot->id, $group->id, $group->name);
                    $removedCount++;
                    $removedGroupIds[] = $group->group_id;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "检查完成，更新了 {$removedCount} 个被移除的群组",
                'data' => ['removed_count' => $removedCount, 'removed_group_ids' => $removedGroupIds]
            ]);
        } catch (\Exception $e) {
            Log::error("检查被移除群组失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 清理指定群组中不在群内的用户关系
     */
    public function cleanupGroupUsers(Request $request, $id)
    {
        try {
            [$bot, $notFound] = $this->findBotOrResponse($id);
            if (!$bot) {
                return $notFound;
            }

            $groupId = $request->input('groupId');
            $currentMemberJids = $request->input('memberJids', []);

            if (empty($groupId)) {
                return response()->json(['success' => false, 'message' => '群组ID不能为空'], 400);
            }

            if (!is_array($currentMemberJids)) {
                return response()->json(['success' => false, 'message' => 'memberJids 必须是数组'], 400);
            }

            $group = Group::where('bot_id', $bot->id)->where('group_id', $groupId)->first();
            if (!$group) {
                return response()->json(['success' => false, 'message' => '群组不存在'], 404);
            }

            // 获取群内所有活跃用户
            $activeUsers = WhatsappUser::where('group_id', $group->id)
                ->where('is_active', true)
                ->get();

            $removedCount = 0;
            $removedUserIds = [];
            $now = now();

            foreach ($activeUsers as $user) {
                if (!in_array($user->jid, $currentMemberJids)) {
                    // 用户不在当前群成员列表中，标记为已退出
                    $user->update([
                        'is_active' => false,
                        'left_at' => $now,
                        'removed_by_admin' => false, // 清理时默认为主动退出
                    ]);

                    $removedCount++;
                    $removedUserIds[] = $user->id;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "清理完成，更新了 {$removedCount} 个不在群内的用户",
                'data' => ['group_id' => $group->id, 'removed_count' => $removedCount, 'removed_user_ids' => $removedUserIds]
            ]);
        } catch (\Exception $e) {
            Log::error("清理群组用户失败: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

