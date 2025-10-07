<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Bot;
use App\Models\Group;
use App\Models\GroupEvent;
use Illuminate\Support\Facades\Cache;

// 接收 QR 码（移除认证中间件）
Route::post('bots/{id}/qr-code', function (Request $request, $id) {
    try {
        \Log::info("收到 QR 码请求，机器人 ID: {$id}");
        \Log::info("请求数据: " . json_encode($request->all()));
        
        $bot = Bot::findOrFail($id);
        
        $qrCode = $request->input('qrCode');
        
        if (empty($qrCode)) {
            \Log::error("QR 码数据为空");
            return response()->json([
                'success' => false,
                'message' => 'QR 码数据为空'
            ], 400);
        }
        
        // 将 QR 码存储到缓存（5分钟有效）
        Cache::put("bot_{$id}_qrcode", $qrCode, now()->addMinutes(5));
        
        // 验证缓存是否成功
        $cached = Cache::get("bot_{$id}_qrcode");
        \Log::info("QR 码已存储到缓存，验证: " . ($cached ? '成功' : '失败'));
        
        $bot->update([
            'status' => 'connecting',
        ]);
        
        \Log::info("机器人 #{$id} QR 码处理完成");
        
        return response()->json([
            'success' => true,
            'message' => 'QR 码已接收'
        ]);
    } catch (\Exception $e) {
        \Log::error("接收 QR 码失败: " . $e->getMessage());
        \Log::error("堆栈跟踪: " . $e->getTraceAsString());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
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

// 机器人状态更新接口
Route::post('/bots/{id}/status', function (Request $request, $id) {
    $bot = Bot::findOrFail($id);
    
    $updateData = [
        'status' => $request->input('status'),
        'last_seen' => now(),
    ];

    // 如果提供了手机号，更新手机号
    if ($request->has('phone_number') && $request->input('phone_number')) {
        $updateData['phone_number'] = $request->input('phone_number');
        \Log::info("机器人 #{$id} 手机号更新为: " . $request->input('phone_number'));
    }
    
    $bot->update($updateData);
    
    // 如果状态变为 online，清除 QR 码缓存
    if ($request->input('status') === 'online') {
        Cache::forget("bot_{$id}_qrcode");
    }
    
    \Log::info("机器人 #{$id} 状态更新为: " . $request->input('status'));
    
    return response()->json([
        'success' => true,
        'message' => '状态更新成功',
        'data' => $bot
    ]);
});

// 更新机器人最后活跃时间
Route::post('/bots/{id}/last-seen', function (Request $request, $id) {
    $bot = Bot::findOrFail($id);
    
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
    $bot = Bot::findOrFail($id);
    
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
    $bot = Bot::findOrFail($id);

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

// 同步群组用户数据
Route::post('/bots/{id}/sync-group-user', function (Request $request, $id) {
    try {
        \Log::info("收到用户同步请求", [
            'bot_id' => $id,
            'request_data' => $request->all()
        ]);

        $bot = Bot::findOrFail($id);

        // 查找或创建群组
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

        // 清理和验证数据
        $phoneNumber = $request->input('phoneNumber');
        $nickname = $request->input('nickname');
        $profilePicture = $request->input('profilePicture');

        // 确保昵称可以正确存储（保持原始格式）
        // 不进行编码转换，保持表情符号的原始格式

        // 查找或创建 WhatsApp 用户
        $whatsappUser = \App\Models\WhatsappUser::updateOrCreate(
            [
                'phone_number' => $phoneNumber,
            ],
            [
                'nickname' => $nickname,
                'profile_picture' => $profilePicture,
            ]
        );

        \Log::info("用户创建/更新成功", [
            'user_id' => $whatsappUser->id,
            'phone_number' => $phoneNumber,
            'nickname' => $nickname
        ]);

        // 处理日期时间格式
        $joinedAt = $request->input('joinedAt');
        if ($joinedAt) {
            // 将 ISO 格式转换为 MySQL 格式
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
                'left_at' => null, // 重置离开时间为空
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
            'message' => '用户同步成功',
            'data' => [
                'group_id' => $group->id,
                'whatsapp_user_id' => $whatsappUser->id,
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error("用户同步失败", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => '用户同步失败: ' . $e->getMessage()
        ], 500);
    }
});

// 启动机器人
Route::post('/bots/{id}/start', function (Request $request, $id) {
    $bot = Bot::findOrFail($id);
    
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
    $bot = Bot::findOrFail($id);
    
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
    $bot = Bot::findOrFail($id);
    
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

        $bot = Bot::findOrFail($id);

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
