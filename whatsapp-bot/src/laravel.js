import axios from 'axios';
import { config } from './config.js';

const BOT_NOT_FOUND_PATTERN = /No query results for model \[App\\Models\\Bot]/;

let handleBotDeletion = null;
let isBotDeleted = null;

export function setBotDeletionHandler(handler) {
    handleBotDeletion = handler;
}

export function setIsBotDeletedChecker(checker) {
    isBotDeleted = checker;
}

export const laravel = {
    async request(endpoint, data) {
        const botIdMatch = endpoint.match(/\/api\/bots\/([^/]+)/);
        const targetBotId = botIdMatch ? botIdMatch[1] : null;

        // 检查机器人是否被删除（需要从外部传入检查函数）
        if (targetBotId && isBotDeleted && typeof isBotDeleted === 'function' && isBotDeleted(targetBotId)) {
            return false;
        }

        try {
            const response = await axios.post(`${config.laravelUrl}${endpoint}`, data, {
                timeout: 15000,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            
            // 检查响应状态
            if (response.data && response.data.success === false) {
                const message = response.data.message || 'unknown error';
                console.error(`❌ Laravel API 业务逻辑错误 [${endpoint}]: ${message}`);
                // 只有在非登录相关的 API 调用时才触发删除逻辑
                // 登录相关的 API（qr-code, pairing-code, status）不应该触发删除
                const isLoginRelated = endpoint.includes('/qr-code') || 
                                     endpoint.includes('/pairing-code') || 
                                     endpoint.includes('/status');
                if (targetBotId && BOT_NOT_FOUND_PATTERN.test(message) && handleBotDeletion && !isLoginRelated) {
                    await handleBotDeletion(targetBotId, message);
                }
                return false;
            }
            
            return true;
        } catch (error) {
            if (error.response) {
                // 服务器响应了错误状态码
                const responseMessage = error.response.data?.message || error.message;
                // 只有在非登录相关的 API 调用时才触发删除逻辑
                const isLoginRelated = endpoint.includes('/qr-code') || 
                                     endpoint.includes('/pairing-code') || 
                                     endpoint.includes('/status');
                if (targetBotId && BOT_NOT_FOUND_PATTERN.test(responseMessage || '') && handleBotDeletion && !isLoginRelated) {
                    await handleBotDeletion(targetBotId, responseMessage);
                    return false;
                }
                console.error(`❌ Laravel API 失败 [${endpoint}]: ${error.response.status} - ${responseMessage}`);
            } else if (error.request) {
                // 请求已发出但没有收到响应
                console.error(`❌ Laravel API 超时 [${endpoint}]: 请求超时或网络错误`);
            } else {
                // 其他错误
                console.error(`❌ Laravel API 错误 [${endpoint}]: ${error.message}`);
            }
            return false;
        }
    },
    
    updateStatus(sessionId, status, phoneNumber = null, message = null) {
        return this.request(`/api/bots/${sessionId}/status`, { status, phone_number: phoneNumber, message });
    },
    
    sendQrCode(sessionId, qrCode) {
        return this.request(`/api/bots/${sessionId}/qr-code`, { qrCode });
    },
    
    sendPairingCode(sessionId, pairingCode, phoneNumber) {
        return this.request(`/api/bots/${sessionId}/pairing-code`, { pairingCode, phoneNumber });
    },
    
    async syncGroup(sessionId, group) {
        return this.request(`/api/bots/${sessionId}/sync-group`, {
            groupId: group.id,
            name: group.subject,
            description: '',
            memberCount: group.size
        });
    },
    
    async syncMember(sessionId, groupId, member) {
        // 发送完整的用户信息，包括 LID 用户
        return this.request(`/api/bots/${sessionId}/sync-group-user`, {
            groupId,
            phoneNumber: member.phone, // 可能是 null（LID 用户）
            whatsappUserId: member.whatsappUserId, // participants 中的 id 字段
            lid: member.lid, // participants 中的 lid 字段
            jid: member.jid, // 可能是 null（LID 用户）
            isAdmin: member.isAdmin,
            joinedAt: new Date().toISOString()
        });
    },
    
    async syncMembers(sessionId, groupId, members) {
        // 批量发送用户信息
        return this.request(`/api/bots/${sessionId}/sync-group-users`, {
            groupId,
            members: members.map(member => ({
                phoneNumber: member.phone, // 可能是 null（LID 用户）
                whatsappUserId: member.whatsappUserId, // participants 中的 id 字段
                lid: member.lid, // participants 中的 lid 字段
                jid: member.jid, // 可能是 null（LID 用户）
                isAdmin: member.isAdmin || false
            }))
        });
    },
    
    async groupJoined(sessionId, groupId, groupName, memberCount) {
        return this.request(`/api/bots/${sessionId}/group-joined`, {
            groupId,
            name: groupName,
            memberCount
        });
    },
    
    async groupLeft(sessionId, groupId) {
        return this.request(`/api/bots/${sessionId}/group-left`, {
            groupId
        });
    },
    
    async memberRemoved(sessionId, groupId, member) {
        return this.request(`/api/bots/${sessionId}/member-removed`, {
            groupId,
            phoneNumber: member.phone,
            whatsappUserId: member.whatsappUserId,
            lid: member.lid,
            jid: member.jid
        });
    },
    
    async memberLeft(sessionId, groupId, member) {
        return this.request(`/api/bots/${sessionId}/member-left`, {
            groupId,
            phoneNumber: member.phone,
            whatsappUserId: member.whatsappUserId,
            lid: member.lid,
            jid: member.jid
        });
    },
    
    async checkRemovedGroups(sessionId, groupIds) {
        return this.request(`/api/bots/${sessionId}/check-removed-groups`, {
            groupIds
        });
    },
    
    async cleanupRemovedUsers(sessionId, groupIds) {
        return this.request(`/api/bots/${sessionId}/cleanup-removed-users`, {
            groupIds
        });
    },
    
    async cleanupGroupUsers(sessionId, groupId, memberJids) {
        return this.request(`/api/bots/${sessionId}/cleanup-group-users`, {
            groupId,
            memberJids
        });
    }
};

