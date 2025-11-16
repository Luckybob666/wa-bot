<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupEvent;
use App\Models\WhatsappUser;
use Illuminate\Support\Facades\DB;

class GroupEventService
{
    /**
     * 记录成员加入事件
     */
    public static function recordMemberJoined(int $botId, int $groupId, WhatsappUser $user, ?string $phoneNumber = null, ?string $jid = null): void
    {
        $group = Group::find($groupId);
        if (!$group) {
            return;
        }

        // 确保手机号不为空：优先使用传入的 phoneNumber，其次使用用户的 phone_number，最后从 JID 中提取
        $finalPhoneNumber = $phoneNumber;
        if (empty($finalPhoneNumber)) {
            $finalPhoneNumber = $user->phone_number;
        }
        if (empty($finalPhoneNumber) && $jid) {
            // 从 JID 中提取手机号：格式如 60147954892@s.whatsapp.net
            $jidParts = explode('@', $jid);
            if (!empty($jidParts[0])) {
                $phonePart = explode(':', $jidParts[0])[0]; // 处理带设备ID的情况
                $finalPhoneNumber = preg_replace('/[^0-9]/', '', $phonePart);
            }
        }

        GroupEvent::create([
            'bot_id' => $botId,
            'group_id' => $groupId,
            'whatsapp_user_id' => $user->id,
            'event_type' => GroupEvent::EVENT_MEMBER_JOINED,
            'event_data' => [
                'whatsapp_user_id' => $user->id,
                'phone_number' => $finalPhoneNumber ?: ($user->phone_number ?? ($user->whatsapp_user_id ?? '未知用户')),
                'jid' => $jid ?: $user->jid,
            ],
        ]);
    }

    /**
     * 记录成员退出事件
     */
    public static function recordMemberLeft(int $botId, int $groupId, WhatsappUser $user, ?string $phoneNumber = null, ?string $jid = null): void
    {
        $group = Group::find($groupId);
        if (!$group) {
            return;
        }

        // 确保手机号不为空：优先使用传入的 phoneNumber，其次使用用户的 phone_number，最后从 JID 中提取
        $finalPhoneNumber = $phoneNumber;
        if (empty($finalPhoneNumber)) {
            $finalPhoneNumber = $user->phone_number;
        }
        if (empty($finalPhoneNumber) && $jid) {
            // 从 JID 中提取手机号：格式如 60147954892@s.whatsapp.net
            $jidParts = explode('@', $jid);
            if (!empty($jidParts[0])) {
                $phonePart = explode(':', $jidParts[0])[0]; // 处理带设备ID的情况
                $finalPhoneNumber = preg_replace('/[^0-9]/', '', $phonePart);
            }
        }

        GroupEvent::create([
            'bot_id' => $botId,
            'group_id' => $groupId,
            'whatsapp_user_id' => $user->id,
            'event_type' => GroupEvent::EVENT_MEMBER_LEFT,
            'event_data' => [
                'whatsapp_user_id' => $user->id,
                'phone_number' => $finalPhoneNumber ?: ($user->phone_number ?? ($user->whatsapp_user_id ?? '未知用户')),
                'jid' => $jid ?: $user->jid,
            ],
        ]);
    }

    /**
     * 记录成员被移除事件
     */
    public static function recordMemberRemoved(int $botId, int $groupId, WhatsappUser $user, ?string $phoneNumber = null, ?string $jid = null): void
    {
        $group = Group::find($groupId);
        if (!$group) {
            return;
        }

        // 确保手机号不为空：优先使用传入的 phoneNumber，其次使用用户的 phone_number，最后从 JID 中提取
        $finalPhoneNumber = $phoneNumber;
        if (empty($finalPhoneNumber)) {
            $finalPhoneNumber = $user->phone_number;
        }
        if (empty($finalPhoneNumber) && $jid) {
            // 从 JID 中提取手机号：格式如 60147954892@s.whatsapp.net
            $jidParts = explode('@', $jid);
            if (!empty($jidParts[0])) {
                $phonePart = explode(':', $jidParts[0])[0]; // 处理带设备ID的情况
                $finalPhoneNumber = preg_replace('/[^0-9]/', '', $phonePart);
            }
        }

        GroupEvent::create([
            'bot_id' => $botId,
            'group_id' => $groupId,
            'whatsapp_user_id' => $user->id,
            'event_type' => GroupEvent::EVENT_MEMBER_REMOVED,
            'event_data' => [
                'whatsapp_user_id' => $user->id,
                'phone_number' => $finalPhoneNumber ?: ($user->phone_number ?? ($user->whatsapp_user_id ?? '未知用户')),
                'jid' => $jid ?: $user->jid,
            ],
        ]);
    }

    /**
     * 记录机器人加入群组事件
     */
    public static function recordBotJoinedGroup(int $botId, int $groupId, string $groupName, int $memberCount, bool $isRejoin = false): void
    {
        GroupEvent::create([
            'bot_id' => $botId,
            'group_id' => $groupId,
            'event_type' => GroupEvent::EVENT_BOT_JOINED_GROUP,
            'event_data' => [
                'group_name' => $groupName,
                'member_count' => $memberCount,
                'is_rejoin' => $isRejoin,
            ],
        ]);
    }

    /**
     * 记录机器人退出群组事件
     */
    public static function recordBotLeftGroup(int $botId, int $groupId, string $groupName): void
    {
        GroupEvent::create([
            'bot_id' => $botId,
            'group_id' => $groupId,
            'event_type' => GroupEvent::EVENT_BOT_LEFT_GROUP,
            'event_data' => [
                'group_name' => $groupName,
            ],
        ]);
    }
}

