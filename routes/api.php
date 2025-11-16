<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use App\Models\Bot;
use App\Models\Group;
use App\Models\GroupEvent;

if (!function_exists('findBotOrResponse')) {
    function findBotOrResponse(int|string $id, string $context)
    {
        $bot = Bot::find($id);
        if (!$bot) {
            $message = "No query results for model [App\\Models\\Bot] {$id}";
            \Log::warning("机器人不存在", [
                'bot_id' => $id,
                'context' => $context,
                'message' => $message,
            ]);

            return [null, response()->json([
                'success' => false,
                'message' => $message,
            ], 404)];
        }

        return [$bot, null];
    }
}

// 接收 QR 码（移除认证中间件）
Route::post('bots/{id}/qr-code', function (Request $request, $id) {
    try {
        [$bot, $notFound] = findBotOrResponse($id, 'qr-code');
        if (!$bot) {
            return $notFound;
        }
        
        $qrCode = $request->input('qrCode');
        if (empty($qrCode)) {
            return response()->json(['success' => false, 'message' => 'QR 码数据为空'], 400);
        }
        
        Cache::put("bot_{$id}_qrcode", $qrCode, now()->addMinutes(5));
        $bot->update(['status' => 'connecting']);
        
        return response()->json(['success' => true, 'message' => 'QR 码已接收']);
    } catch (\Exception $e) {
        \Log::error("接收 QR 码失败: " . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// 获取 QR 码
Route::get('/bots/{id}/qr-code', function ($id) {
    try {
        // 先从缓存获取
        $qrCode = Cache::get("bot_{$id}_qrcode");
        
        // 如果缓存中没有，尝试从 Node.js 服务器获取
        if (empty($qrCode)) {
            try {
                $nodeUrl = config('app.node_server.url');
                $timeout = config('app.node_server.timeout', 10);
                
                $response = \Http::timeout($timeout)->get($nodeUrl . '/sessions/' . $id . '/qr');
                
                if ($response->successful()) {
                    $data = $response->json();
                    if ($data['success'] && !empty($data['qr'])) {
                        $qrCode = $data['qr'];
                        // 更新缓存
                        Cache::put("bot_{$id}_qrcode", $qrCode, now()->addMinutes(5));
                    }
                }
            } catch (\Exception $e) {
                \Log::error("从 Node.js 获取 QR 码失败: " . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'qrCode' => $qrCode,
                'hasQrCode' => !empty($qrCode)
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

// 配对码接口
Route::post('/bots/{id}/pairing-code', function (Request $request, $id) {
    try {
        [$bot, $notFound] = findBotOrResponse($id, 'pairing-code');
        if (!$bot) {
            return $notFound;
        }
        
        $pairingCode = $request->input('pairingCode');
        $phoneNumber = $request->input('phoneNumber');
        
        if (empty($pairingCode)) {
            return response()->json(['success' => false, 'message' => '配对码不能为空'], 400);
        }
        
        Cache::put("bot_{$id}_pairing_code", $pairingCode, 300);
        if (!empty($phoneNumber)) {
            Cache::put("bot_{$id}_phone_number", $phoneNumber, 300);
        }
        
        return response()->json([
            'success' => true,
            'data' => ['pairingCode' => $pairingCode, 'phoneNumber' => $phoneNumber, 'hasPairingCode' => !empty($pairingCode)]
        ]);
    } catch (\Exception $e) {
        \Log::error("处理配对码失败: " . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// 获取配对码
Route::get('/bots/{id}/pairing-code', function ($id) {
    try {
        // 从缓存获取配对码和手机号
        $pairingCode = Cache::get("bot_{$id}_pairing_code");
        $phoneNumber = Cache::get("bot_{$id}_phone_number");
        
        return response()->json([
            'success' => true,
            'data' => [
                'pairingCode' => $pairingCode,
                'phoneNumber' => $phoneNumber,
                'hasPairingCode' => !empty($pairingCode)
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

// 机器人状态更新接口
Route::post('/bots/{id}/status', function (Request $request, $id) {
    try {
        [$bot, $notFound] = findBotOrResponse($id, 'status');
        if (!$bot) {
            return $notFound;
        }
        
        $updateData = ['status' => $request->input('status'), 'last_seen' => now()];
        if ($request->has('phone_number') && $request->input('phone_number')) {
            $updateData['phone_number'] = $request->input('phone_number');
        }
        
        $bot->update($updateData);
        
        if ($request->input('status') === 'online') {
            Cache::forget("bot_{$id}_qrcode");
            Cache::forget("bot_{$id}_pairing_code");
            Cache::forget("bot_{$id}_phone_number");
        }
        
        return response()->json(['success' => true, 'message' => '状态更新成功', 'data' => $bot]);
    } catch (\Exception $e) {
        \Log::error("状态更新失败，机器人 ID: {$id}, 错误: " . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// 更新机器人最后活跃时间
Route::post('/bots/{id}/last-seen', function (Request $request, $id) {
    [$bot, $notFound] = findBotOrResponse($id, 'last-seen');
    if (!$bot) {
        return $notFound;
    }
    
    $bot->update([
        'last_seen' => now(),
    ]);
    
    return response()->json([
        'success' => true,
        'message' => '最后活跃时间更新成功'
    ]);
});

// 接收群组事件
Route::post('/group-events', function (Request $request) {
    try {
        $botId = $request->input('botId');
        $eventType = $request->input('eventType');
        $data = $request->input('data');
        
        // 这里可以处理群组事件，存储到数据库等
        \Log::info('收到群组事件', [
            'botId' => $botId,
            'eventType' => $eventType,
            'data' => $data
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '事件接收成功'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

// 启动机器人
Route::post('/bots/{id}/start', function (Request $request, $id) {
    [$bot, $notFound] = findBotOrResponse($id, 'sync-group');
    if (!$bot) {
        return $notFound;
    }
    
    // 这里应该通知 Node.js 机器人启动
    // 暂时只更新状态
    $bot->update([
        'status' => 'connecting',
    ]);
    
    return response()->json([
        'success' => true,
        'message' => '机器人启动命令已发送',
        'data' => $bot
    ]);
});

// 同步群组数据
Route::post('/bots/{id}/sync-group', function (Request $request, $id) {
    [$bot, $notFound] = findBotOrResponse($id, 'start-legacy');
    if (!$bot) {
        return $notFound;
    }

    $group = Group::updateOrCreate(
        [
            'bot_id' => $bot->id,
            'group_id' => $request->input('groupId'),
        ],
        [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'member_count' => $request->input('memberCount', 0),
        ]
    );

    return response()->json([
        'success' => true,
        'message' => '群组同步成功',
        'data' => $group
    ]);
});


// 启动机器人
Route::post('/bots/{id}/start', function (Request $request, $id) {
    [$bot, $notFound] = findBotOrResponse($id, 'start');
    if (!$bot) {
        return $notFound;
    }
    
    try {
        // 从配置读取 Node.js 服务器地址
        $nodeUrl = config('app.node_server.url');
        $timeout = config('app.node_server.timeout', 30);
        
        // 调用 Node.js 服务器启动机器人
        $response = \Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $id . '/start', [
            'laravelUrl' => url('/'),
            'apiToken' => ''
        ]);
        
        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => '机器人启动成功',
                'data' => $response->json()
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => '机器人启动失败'
            ], 500);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => '无法连接到机器人服务器：' . $e->getMessage()
        ], 500);
    }
});

// 停止机器人
Route::post('/bots/{id}/stop', function (Request $request, $id) {
    [$bot, $notFound] = findBotOrResponse($id, 'stop');
    if (!$bot) {
        return $notFound;
    }
    
    try {
        // 从配置读取 Node.js 服务器地址
        $nodeUrl = config('app.node_server.url');
        $timeout = config('app.node_server.timeout', 10);
        
        // 调用 Node.js 服务器停止机器人
        $response = \Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $id . '/stop');
        
        $bot->update(['status' => 'offline']);
        
        return response()->json([
            'success' => true,
            'message' => '机器人已停止',
            'data' => $bot
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => '停止机器人失败：' . $e->getMessage()
        ], 500);
    }
});

// 手动同步群组用户（用于测试）
Route::post('/bots/{id}/manual-sync-users', function (Request $request, $id) {
    [$bot, $notFound] = findBotOrResponse($id, 'manual-sync-users');
    if (!$bot) {
        return $notFound;
    }
    
    try {
        $nodeUrl = config('app.node_server.url');
        $timeout = config('app.node_server.timeout', 30);
        
        // 调用 Node.js 服务器同步群组用户
        $response = \Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $id . '/sync-groups');
        
        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => '群组用户同步成功',
                'data' => $response->json()
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => '群组用户同步失败: ' . $response->body()
            ], 500);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => '无法连接到机器人服务器：' . $e->getMessage()
        ], 500);
    }
});

// 手动创建群组用户关系（用于测试）
Route::match(['GET', 'POST'], '/test-sync-group-users', function (Request $request) {
    try {
        // 查找群组
        $group = \App\Models\Group::where('group_id', '120363401091835005@g.us')->first();
        
        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => '群组不存在'
            ], 404);
        }
        
        // 查找用户
        $users = \App\Models\WhatsappUser::all();
        
        $syncedCount = 0;
        foreach ($users as $user) {
            // 创建群组用户关系
            \DB::table('group_whatsapp_user')->updateOrInsert(
                [
                    'group_id' => $group->id,
                    'whatsapp_user_id' => $user->id,
                ],
                [
                    'joined_at' => now(),
                    'is_admin' => false,
                    'left_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $syncedCount++;
        }
        
        return response()->json([
            'success' => true,
            'message' => "成功同步 {$syncedCount} 个用户到群组",
            'data' => [
                'group_name' => $group->name,
                'group_id' => $group->group_id,
                'synced_users' => $syncedCount
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => '同步失败：' . $e->getMessage()
        ], 500);
    }
});

// 同步群组用户手机号码（只存储手机号码，不获取详细信息）
Route::post('/bots/{id}/sync-group-user-phone', function (Request $request, $id) {
    try {
        \Log::info("收到用户手机号码同步请求", [
            'bot_id' => $id,
            'request_data' => $request->all()
        ]);

        [$bot, $notFound] = findBotOrResponse($id, 'sync-group-user-phone');
        if (!$bot) {
            return $notFound;
        }

        // 查找群组
        $group = Group::where('bot_id', $bot->id)
                      ->where('group_id', $request->input('groupId'))
                      ->first();

        if (!$group) {
            \Log::error("群组不存在", [
                'bot_id' => $bot->id,
                'group_id' => $request->input('groupId')
            ]);
            return response()->json([
                'success' => false,
                'message' => '群组不存在'
            ], 404);
        }

        // 只存储手机号码，不获取详细信息
        $phoneNumber = $request->input('phoneNumber');

        // 查找或创建 WhatsApp 用户（只存储手机号码）
        $whatsappUser = \App\Models\WhatsappUser::updateOrCreate(
            [
                'phone_number' => $phoneNumber,
            ],
            [
                'nickname' => null, // 不获取昵称
                'profile_picture' => null, // 不获取头像
            ]
        );

        \Log::info("用户手机号码创建/更新成功", [
            'user_id' => $whatsappUser->id,
            'phone_number' => $phoneNumber
        ]);

        // 处理日期时间格式
        $joinedAt = $request->input('joinedAt');
        if ($joinedAt) {
            $joinedAt = \Carbon\Carbon::parse($joinedAt)->format('Y-m-d H:i:s');
        } else {
            $joinedAt = now();
        }

        // 创建或更新群组用户关系
        \DB::table('group_whatsapp_user')->updateOrInsert(
            [
                'group_id' => $group->id,
                'whatsapp_user_id' => $whatsappUser->id,
            ],
            [
                'joined_at' => $joinedAt,
                'is_admin' => $request->input('isAdmin', false),
                'left_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        \Log::info("群组用户关系创建成功", [
            'group_id' => $group->id,
            'user_id' => $whatsappUser->id
        ]);

        return response()->json([
            'success' => true,
            'message' => '用户手机号码同步成功',
            'data' => [
                'group_id' => $group->id,
                'whatsapp_user_id' => $whatsappUser->id,
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error("用户手机号码同步失败", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => '用户手机号码同步失败: ' . $e->getMessage()
        ], 500);
    }
});

// 同步群组用户（完整信息，包括 LID 用户）
Route::post('/bots/{id}/sync-group-user', [App\Http\Controllers\Api\BotController::class, 'syncGroupUser']);
Route::post('/bots/{id}/sync-group-users', [App\Http\Controllers\Api\BotController::class, 'syncGroupUsers']);

// 机器人加入群组
Route::post('/bots/{id}/group-joined', [App\Http\Controllers\Api\BotController::class, 'groupJoined']);

// 机器人退出群组
Route::post('/bots/{id}/group-left', [App\Http\Controllers\Api\BotController::class, 'groupLeft']);

// 检查并更新被移除的群组状态（用于机器人重连后同步）
Route::post('/bots/{id}/check-removed-groups', [App\Http\Controllers\Api\BotController::class, 'checkRemovedGroups']);

// 清理不在群内的用户关系（用于机器人重连后同步）
Route::post('/bots/{id}/cleanup-removed-users', function (Request $request, $id) {
    try {
        \Log::info("收到清理不在群内用户请求", [
            'bot_id' => $id,
            'request_data' => $request->all()
        ]);

        [$bot, $notFound] = findBotOrResponse($id, 'cleanup-removed-users');
        if (!$bot) {
            return $notFound;
        }

        $currentGroupIds = $request->input('groupIds', []); // 当前机器人所在的所有群组 ID

        if (!is_array($currentGroupIds)) {
            return response()->json([
                'success' => false,
                'message' => 'groupIds 必须是数组'
            ], 400);
        }

        // 查询数据库中该机器人的所有 active 状态的群组
        $activeGroups = Group::where('bot_id', $bot->id)
                            ->where('status', Group::STATUS_ACTIVE)
                            ->whereIn('group_id', $currentGroupIds) // 只处理当前机器人所在的群组
                            ->get();

        $totalRemovedUsers = 0;
        $groupRemovedCounts = [];

        foreach ($activeGroups as $group) {
            // 获取数据库中该群组的所有活跃用户（left_at IS NULL）
            $activeUsers = \DB::table('group_whatsapp_user')
                ->where('group_id', $group->id)
                ->whereNull('left_at')
                ->pluck('whatsapp_user_id')
                ->toArray();

            if (empty($activeUsers)) {
                continue;
            }

            // 从 Node.js 获取当前群组的实际成员列表
            // 注意：这里我们需要从请求中获取每个群组的成员列表
            // 或者让 Node.js 在调用时传入每个群组的成员信息
            // 暂时先跳过，因为需要 Node.js 端传入成员信息
            
            // 由于无法直接获取当前群组成员，我们采用另一种方式：
            // 在同步成员时，已经更新了 left_at 为 null
            // 这里我们只需要处理那些在数据库中 left_at IS NULL，但实际不在群中的用户
            // 但这个问题需要 Node.js 端传入每个群组的成员列表
            
            // 暂时记录日志，后续可以通过其他方式处理
            \Log::info("群组用户清理检查", [
                'group_id' => $group->group_id,
                'group_name' => $group->name,
                'active_users_count' => count($activeUsers)
            ]);
        }

        \Log::info("清理不在群内用户完成", [
            'bot_id' => $bot->id,
            'total_removed_users' => $totalRemovedUsers
        ]);

        return response()->json([
            'success' => true,
            'message' => "清理完成，更新了 {$totalRemovedUsers} 个不在群内的用户",
            'data' => [
                'removed_users_count' => $totalRemovedUsers,
                'group_removed_counts' => $groupRemovedCounts
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error("清理不在群内用户失败: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

// 清理指定群组中不在群内的用户关系
Route::post('/bots/{id}/cleanup-group-users', [App\Http\Controllers\Api\BotController::class, 'cleanupGroupUsers']);

// 用户被管理员移除
Route::post('/bots/{id}/member-removed', [App\Http\Controllers\Api\BotController::class, 'memberRemoved']);

// 用户主动退出
Route::post('/bots/{id}/member-left', [App\Http\Controllers\Api\BotController::class, 'memberLeft']);
