import express from 'express';
import { WhatsAppSession } from './WhatsAppSession.js';
import { sessionManager } from './sessionManager.js';
import { laravel } from './laravel.js';
import { utils } from './utils.js';

// 创建路由，接受重连回调作为参数
export default function createRoutes(reconnectCallback = null) {
    const router = express.Router();

    const respondSessionRunning = (res, session) =>
        res.json({
            success: true,
            message: `机器人已运行，状态: ${session.status}`,
            data: { botId: session.sessionId, status: session.status }
        });

    const requireOnlineSession = (res, botId) => {
        const session = sessionManager.getSession(botId);
        if (!session || session.status !== 'open') {
            res.status(409).json({ success: false, message: '机器人未在线' });
            return null;
        }
        return session;
    };

    // 健康检查
    router.get('/', (req, res) => {
        res.json({
            success: true,
            message: 'WhatsApp 机器人服务器运行中',
            version: '3.0.0',
            sessions: sessionManager.getSessionsCount()
        });
    });

    // 启动机器人（二维码登录）
    router.post('/api/bot/:botId/start', async (req, res) => {
        try {
            const { botId } = req.params;
            
            const existing = sessionManager.getSession(botId);
            if (existing) {
                return respondSessionRunning(res, existing);
            }
            
            const session = new WhatsAppSession(botId, 'qr', null, sessionManager.isBotDeleted.bind(sessionManager), reconnectCallback);
            await session.create();
            sessionManager.saveSession(botId, session);
            
            res.json({ success: true, message: '机器人启动中', data: { botId, status: 'connecting' } });
        } catch (error) {
            console.error(`❌ 启动失败: ${error.message}`);
            res.status(500).json({ success: false, message: error.message });
        }
    });

    // 启动机器人（验证码登录）
    router.post('/api/bot/:botId/start-sms', async (req, res) => {
        try {
            const { botId } = req.params;
            const { phoneNumber } = req.body;
            
            if (!phoneNumber) {
                return res.status(400).json({ success: false, message: '手机号不能为空' });
            }
            
            const existing = sessionManager.getSession(botId);
            if (existing) {
                return respondSessionRunning(res, existing);
            }
            
            const session = new WhatsAppSession(botId, 'sms', phoneNumber, sessionManager.isBotDeleted.bind(sessionManager), reconnectCallback);
            await session.create();
            sessionManager.saveSession(botId, session);
            
            res.json({ 
                success: true, 
                message: '正在生成配对码...',
                data: { botId, status: 'connecting', pairingCode: null }
            });
        } catch (error) {
            console.error(`❌ 验证码登录失败: ${error.message}`);
            res.status(500).json({ success: false, message: error.message });
        }
    });

    // 停止机器人
    router.post('/api/bot/:botId/stop', async (req, res) => {
        try {
            const { botId } = req.params;
            const { deleteFiles = false } = req.body; // 默认不删除会话文件
            
            const session = sessionManager.getSession(botId);
            if (session) {
                await session.stop(deleteFiles);
                sessionManager.removeSession(botId);
            }
            
            const message = deleteFiles ? '机器人已停止，会话已清理' : '机器人已停止，会话已保留';
            await laravel.updateStatus(botId, 'offline', null, message);
            res.json({ success: true, message });
        } catch (error) {
            console.error(`❌ 停止失败: ${error.message}`);
            res.status(500).json({ success: false, message: error.message });
        }
    });

    // 获取机器人状态
    router.get('/api/bot/:botId', (req, res) => {
        try {
            const { botId } = req.params;
            const session = sessionManager.getSession(botId);
            
            if (!session) {
                return res.json({ success: true, botId, status: 'offline', hasQR: false });
            }
            
            res.json({ 
                success: true, 
                botId, 
                status: session.status, 
                hasQR: !!session.lastQR,
                qr: session.lastQR,
                pairingCode: session.pairingCode,
                phoneNumber: session.phoneNumber
            });
        } catch (error) {
            res.status(500).json({ success: false, message: error.message });
        }
    });

    // 同步群组
    router.post('/api/bot/:botId/sync-groups', async (req, res) => {
        try {
            const { botId } = req.params;
            const session = requireOnlineSession(res, botId);
            if (!session) return;

            const groupsDict = await session.sock.groupFetchAllParticipating();
            const groups = Object.values(groupsDict);
            const currentGroupIds = groups.map(g => g.id);

            let syncedCount = 0;
            let totalMembersSynced = 0;
            
            // 同步所有群组信息和成员
            for (const group of groups) {
                try {
                    // 同步群组基本信息
                    const groupInfo = {
                        id: group.id,
                        subject: group.subject,
                        size: group.participants?.length || 0
                    };
                    if (await laravel.syncGroup(botId, groupInfo)) {
                        syncedCount++;
                    }
                    
                    // 同步群组成员
                    try {
                        const gid = utils.ensureGroupId(group.id);
                        const meta = await session.sock.groupMetadata(gid);
                        const members = (meta.participants || []).map(p => {
                            const jid = p.jid;
                            const phone = utils.jidToPhone(jid);
                            return {
                                jid,
                                whatsappUserId: p.id || (jid ? jid.split('@')[0].split(':')[0] : null),
                                lid: p.lid || p.id || null,
                                phone,
                                isAdmin: !!p.admin
                            };
                        });

                        if (members.length > 0) {
                            const result = await laravel.syncMembers(botId, group.id, members);
                            if (result) {
                                totalMembersSynced += members.length;
                            }
                        }
                        
                        // 清理不在群内的用户
                        const currentMemberJids = members.map(m => m.jid);
                        await laravel.cleanupGroupUsers(botId, group.id, currentMemberJids);
                    } catch (error) {
                        console.error(`❌ 同步群组 ${group.id} 成员失败: ${error.message}`);
                    }
                } catch (error) {
                    console.error(`❌ 同步群组 ${group.id} 失败: ${error.message}`);
                }
            }
            
            // 检查并更新被移除的群组状态
            try {
                await laravel.checkRemovedGroups(botId, currentGroupIds);
            } catch (error) {
                console.error(`❌ 检查被移除群组失败: ${error.message}`);
            }

            res.json({
                success: true,
                message: `成功同步 ${syncedCount}/${groups.length} 个群组，${totalMembersSynced} 个成员`,
                data: { syncedCount, totalGroups: groups.length, totalMembersSynced }
            });
        } catch (error) {
            console.error(`❌ 同步群组失败: ${error.message}`);
            res.status(500).json({ success: false, message: error.message });
        }
    });

    // 同步群组用户
    router.post('/api/bot/:botId/sync-group-users', async (req, res) => {
        try {
            const { botId } = req.params;
            const { groupId } = req.body;
            const session = requireOnlineSession(res, botId);
            if (!session) return;

            const gid = utils.ensureGroupId(groupId);
            const meta = await session.sock.groupMetadata(gid);
            const members = (meta.participants || []).map(p => {
                // 直接使用participants中的jid字段，这是真实的手机号
                const jid = p.jid; // 如：60147954892@s.whatsapp.net
                const phone = utils.jidToPhone(jid);
                return {
                    jid,
                    whatsappUserId: jid.split('@')[0].split(':')[0],
                    phone,
                    isAdmin: !!p.admin
                };
            });

            let syncedCount = 0;
            for (const member of members) {
                if (await laravel.syncMember(botId, groupId, member)) syncedCount++;
            }

            res.json({
                success: true,
                message: `成功同步 ${syncedCount}/${members.length} 个用户`,
                data: {
                    groupName: meta.subject,
                    groupId,
                    syncedCount,
                    totalMembers: members.length
                }
            });
        } catch (error) {
            console.error(`❌ 同步用户失败: ${error.message}`);
            res.status(500).json({ success: false, message: error.message });
        }
    });

    // 列出所有会话
    router.get('/sessions', (req, res) => {
        const list = Array.from(sessionManager.getAllSessions().entries()).map(([id, session]) => ({
            sessionId: id,
            status: session.status || 'connecting',
            hasQR: !!session.lastQR,
            phoneNumber: session.phoneNumber
        }));
        res.json({ success: true, data: { total: sessionManager.getSessionsCount(), sessions: list } });
    });

    return router;
}

