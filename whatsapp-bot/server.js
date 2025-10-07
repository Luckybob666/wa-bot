require('dotenv').config();
const express = require('express');
const cors = require('cors');
const pino = require('pino');
const fs = require('fs');
const path = require('path');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    jidNormalizedUser
} = require('@whiskeysockets/baileys');
const qrcode = require('qrcode');
const axios = require('axios');

const logger = pino({ level: 'info' });
const app = express();
const PORT = process.env.PORT || 3000;
const LARAVEL_URL = process.env.LARAVEL_URL || 'http://localhost:89';


app.use(cors());
app.use(express.json());

/**
 * 会话管理：
 * sessions: Map<sessionId, { sock, state, saveCreds, status, lastQR }>
 * status: 'connecting' | 'open' | 'close'
 */
const sessions = new Map();

const SESSIONS_DIR = path.join(__dirname, 'sessions');
if (!fs.existsSync(SESSIONS_DIR)) fs.mkdirSync(SESSIONS_DIR, { recursive: true });

/** 工具函数 */
const ensureGroupId = (gid) => gid.endsWith('@g.us') ? gid : `${gid}@g.us`;
const phoneToUserJid = (phone) => {
    const digits = (phone || '').replace(/\D/g, '');
    return `${digits}@s.whatsapp.net`;
};
const jidToPhone = (jid) => (jid || '').split('@')[0];

/** 创建 Laravel API 请求配置 */
const createLaravelConfig = () => ({
    timeout: 15000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

/** 发送状态到 Laravel */
async function sendStatusToLaravel(sessionId, status, phoneNumber = null, message = null) {
    try {
        await axios.post(`${LARAVEL_URL}/api/bots/${sessionId}/status`, {
            status: status,
            phone_number: phoneNumber,
            message: message
        }, createLaravelConfig());
        console.log(`✅ 机器人 #${sessionId} 状态已更新到 Laravel: ${status}`);
    } catch (error) {
        console.error(`❌ 更新机器人 #${sessionId} 状态到 Laravel 失败: ${error.message}`);
        console.error(`❌ 错误详情: ${error.response?.status} - ${error.response?.statusText}`);
    }
}

/** 发送二维码到 Laravel */
async function sendQrCodeToLaravel(sessionId, qrCode) {
    try {
        await axios.post(`${LARAVEL_URL}/api/bots/${sessionId}/qr-code`, {
            qrCode: qrCode
        }, createLaravelConfig());
        console.log(`✅ 机器人 #${sessionId} QR 码已发送到 Laravel`);
    } catch (error) {
        console.error(`❌ 发送机器人 #${sessionId} QR 码到 Laravel 失败: ${error.message}`);
        console.error(`❌ 错误详情: ${error.response?.status} - ${error.response?.statusText}`);
    }
}

/** 建立/获取一个会话（不存在则创建并连接） */
async function getOrCreateSession(sessionId) {
    if (sessions.has(sessionId)) {
        const existingSession = sessions.get(sessionId);
        if (existingSession.status === 'connecting') {
            console.log(`⏳ 机器人 #${sessionId} 正在连接中，等待完成...`);
            return existingSession;
        }
        return existingSession;
    }

    console.log(`🤖 正在为机器人 #${sessionId} 创建新会话`);
    
    const sessionPath = path.join(SESSIONS_DIR, sessionId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
    const { version } = await fetchLatestBaileysVersion();

    const socketConfig = {
        version,
        auth: state,
        printQRInTerminal: false,
        browser: ['Chrome', 'Windows', '10.0.0'],
        logger: pino({ level: 'info' }),
        connectTimeoutMs: 60000,
        defaultQueryTimeoutMs: 0,
        keepAliveIntervalMs: 30000,
        retryRequestDelayMs: 250,
        maxMsgRetryCount: 5,
        markOnlineOnConnect: true,
        syncFullHistory: false,
        fireInitQueries: true,
        generateHighQualityLinkPreview: false,
        getMessage: async (key) => {
            return {
                conversation: 'Hello'
            };
        }
    };


    const sock = makeWASocket(socketConfig);

    const ctx = {
        sock,
        state,
        saveCreds,
        status: 'connecting',
        lastQR: null
    };
    sessions.set(sessionId, ctx);

    // 监听凭据更新
    sock.ev.on('creds.update', saveCreds);

    // 连接状态 & 二维码
    sock.ev.on('connection.update', async (update) => {
        const { connection, qr, lastDisconnect, isNewLogin } = update;

        console.log(`📊 机器人 #${sessionId} 连接状态更新: ${connection || 'unknown'}`);

        if (qr) {
            console.log(`📱 机器人 #${sessionId} 收到 QR 码`);
            try {
                // 生成 base64 dataURL
                ctx.lastQR = await qrcode.toDataURL(qr);
                await sendQrCodeToLaravel(sessionId, ctx.lastQR);
                await sendStatusToLaravel(sessionId, 'connecting', null, '等待扫码登录');
            } catch (e) {
                console.error('❌ QR 码生成失败:', e.message);
            }
        }

        if (connection === 'open') {
            ctx.status = 'open';
            ctx.lastQR = null;
            const phoneNumber = sock.user.id.split(':')[0];
            const pushname = sock.user.name || null;
            
            console.log(`✅ 机器人 #${sessionId} 连接成功！手机号: ${phoneNumber}, 昵称: ${pushname || '未设置'}`);
            await sendStatusToLaravel(sessionId, 'online', phoneNumber, '连接成功');
        }

        if (connection === 'close') {
            const reason = lastDisconnect?.error?.output?.statusCode || lastDisconnect?.error?.reason || 'unknown';
            const shouldReconnect = (lastDisconnect?.error)?.output?.statusCode !== DisconnectReason.loggedOut;
            
            console.log(`❌ 机器人 #${sessionId} 连接断开，原因: ${reason}，是否重连: ${shouldReconnect ? '是' : '否'}`);
            ctx.status = 'close';
            
            if (shouldReconnect) {
                await sendStatusToLaravel(sessionId, 'offline', null, `连接断开，尝试重连中...`);
                
                // 延迟重连
                setTimeout(async () => {
                    try {
                        console.log(`🔄 机器人 #${sessionId} 尝试重连...`);
                        await sendStatusToLaravel(sessionId, 'connecting', null, '正在重连...');
                        await getOrCreateSession(sessionId);
                    } catch (error) {
                        console.error(`❌ 机器人 #${sessionId} 重连失败: ${error.message}`);
                        await sendStatusToLaravel(sessionId, 'error', null, `重连失败: ${error.message}`);
                    }
                }, 5000); // 5秒后重连
            } else {
                await sendStatusToLaravel(sessionId, 'offline', null, '已登出');
                // 删除会话
                sessions.delete(sessionId);
            }
        }
    });

    return ctx;
}

/** API 端点 */

// 健康检查
app.get('/', (req, res) => {
    res.json({
        success: true,
        message: 'WhatsApp 机器人服务器运行中',
        version: '2.0.0',
        runningSessions: sessions.size
    });
});

// 列出所有会话
app.get('/sessions', async (req, res) => {
    const list = [];
    for (const [sessionId, ctx] of sessions.entries()) {
        list.push({ 
            sessionId, 
            status: ctx.status || 'connecting', 
            hasQR: !!ctx.lastQR 
        });
    }
    res.json({ success: true, data: { total: sessions.size, sessions: list } });
});

// 获取二维码（触发登录流程）
app.get('/sessions/:sessionId/qr', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const ctx = await getOrCreateSession(sessionId);
        res.json({
            success: true,
            sessionId,
            status: ctx.status,
            qr: ctx.lastQR
        });
    } catch (e) {
        console.error('❌ 获取 QR 码失败:', e.message);
        res.status(500).json({ 
            success: false, 
            error: 'failed_to_get_qr', 
            detail: String(e?.message || e) 
        });
    }
});

// 获取会话状态
app.get('/sessions/:sessionId/status', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const ctx = await getOrCreateSession(sessionId);
        res.json({ 
            success: true, 
            sessionId, 
            status: ctx.status, 
            hasQR: !!ctx.lastQR 
        });
    } catch (e) {
        console.error('❌ 获取会话状态失败:', e.message);
        res.status(500).json({ 
            success: false, 
            error: 'failed_to_get_status', 
            detail: String(e?.message || e) 
        });
    }
});

// 停止会话
app.post('/sessions/:sessionId/stop', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const ctx = sessions.get(sessionId);
        
        if (ctx) {
            await ctx.sock.logout();
            sessions.delete(sessionId);
            console.log(`✅ 会话 #${sessionId} 已停止并移除`);
            await sendStatusToLaravel(sessionId, 'offline', null, '用户手动停止');
        }
        
        res.json({ success: true, message: '会话已停止' });
    } catch (e) {
        console.error('❌ 停止会话失败:', e.message);
        res.status(500).json({ 
            success: false, 
            error: 'failed_to_stop_session', 
            detail: String(e?.message || e) 
        });
    }
});

// 获取群组列表
app.get('/sessions/:sessionId/groups', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const ctx = await getOrCreateSession(sessionId);
        
        if (ctx.status !== 'open') {
            return res.status(409).json({ 
                success: false, 
                error: 'not_connected', 
                message: 'session not connected yet' 
            });
        }

        const groupsDict = await ctx.sock.groupFetchAllParticipating();
        const groups = Object.values(groupsDict).map(g => ({
            id: g.id,
            subject: g.subject,
            size: g.participants?.length || 0
        }));

        // 同步到 Laravel
        for (const group of groups) {
            try {
                await axios.post(`${LARAVEL_URL}/api/bots/${sessionId}/sync-group`, {
                    groupId: group.id,
                    name: group.subject,
                    description: '',
                    memberCount: group.size
                }, createLaravelConfig());
            } catch (error) {
                console.error(`❌ 同步群组 "${group.subject}" 到 Laravel 失败: ${error.message}`);
            }
        }

        res.json({ 
            success: true, 
            sessionId, 
            groups,
            syncedCount: groups.length 
        });
    } catch (e) {
        console.error('❌ 获取群组列表失败:', e.message);
        res.status(500).json({ 
            success: false, 
            error: 'failed_to_fetch_groups', 
            detail: String(e?.message || e) 
        });
    }
});

// 获取指定群组的成员手机号
app.get('/sessions/:sessionId/groups/:groupId/members', async (req, res) => {
    try {
        const { sessionId, groupId } = req.params;
        const gid = ensureGroupId(groupId);

        const ctx = await getOrCreateSession(sessionId);
        if (ctx.status !== 'open') {
            return res.status(409).json({ 
                success: false, 
                error: 'not_connected', 
                message: 'session not connected yet' 
            });
        }

        const meta = await ctx.sock.groupMetadata(gid);
        const members = (meta.participants || []).map(p => {
            const jid = jidNormalizedUser(p.id);
            return {
                jid,
                phone: jidToPhone(jid),
                admin: p.admin || null,
                isAdmin: !!p.admin
            };
        });

        // 同步到 Laravel
        for (const member of members) {
            try {
                await axios.post(`${LARAVEL_URL}/api/bots/${sessionId}/sync-group-user-phone`, {
                    groupId: groupId,
                    phoneNumber: member.phone,
                    isAdmin: member.isAdmin,
                    joinedAt: new Date().toISOString()
                }, createLaravelConfig());
            } catch (error) {
                console.error(`❌ 同步成员 ${member.phone} 到 Laravel 失败: ${error.message}`);
            }
        }

        res.json({
            success: true,
            sessionId,
            groupId: meta.id,
            subject: meta.subject,
            count: members.length,
            syncedCount: members.length,
            members
        });
    } catch (e) {
        console.error('❌ 获取群组成员失败:', e.message);
        res.status(500).json({ 
            success: false, 
            error: 'failed_to_fetch_members', 
            detail: String(e?.message || e) 
        });
    }
});

// 兼容 Laravel 的 API 端点（保持向后兼容）
app.post('/api/bot/:botId/start', async (req, res) => {
    const { botId } = req.params;
    console.log(`📥 收到启动请求 - 机器人 ID: ${botId}`);
    
    try {
        // 先检查是否已有会话
        if (sessions.has(botId)) {
            const existingSession = sessions.get(botId);
            console.log(`ℹ️ 机器人 #${botId} 已有会话，状态: ${existingSession.status}`);
            return res.json({ 
                success: true, 
                message: `机器人已存在，状态: ${existingSession.status}`, 
                data: { botId, status: existingSession.status } 
            });
        }
        
        await getOrCreateSession(botId);
        res.json({ success: true, message: '机器人启动中...', data: { botId, status: 'connecting' } });
    } catch (error) {
        console.error(`❌ 启动机器人 #${botId} 失败: ${error.message}`);
        res.status(500).json({ success: false, message: `启动机器人失败: ${error.message}` });
    }
});

app.post('/api/bot/:botId/stop', async (req, res) => {
    const { botId } = req.params;
    console.log(`🛑 收到停止请求 - 机器人 ID: ${botId}`);
    
    try {
        const ctx = sessions.get(botId);
        if (ctx) {
            await ctx.sock.logout();
            sessions.delete(botId);
            console.log(`✅ 机器人 #${botId} 已停止`);
        }
        res.json({ success: true, message: '机器人已停止' });
    } catch (error) {
        console.error(`❌ 停止机器人 #${botId} 失败: ${error.message}`);
        res.status(500).json({ success: false, message: `停止机器人失败: ${error.message}` });
    }
});

app.post('/api/bot/:botId/sync-groups', async (req, res) => {
    const { botId } = req.params;
    console.log(`🔄 收到同步群组请求 - 机器人 ID: ${botId}`);
    
    try {
        const ctx = await getOrCreateSession(botId);
        if (ctx.status !== 'open') {
            return res.status(400).json({ success: false, message: '机器人未在线' });
        }

        const groupsDict = await ctx.sock.groupFetchAllParticipating();
        const groups = Object.values(groupsDict).map(g => ({
            id: g.id,
            subject: g.subject,
            size: g.participants?.length || 0
        }));

        let syncedCount = 0;
        for (const group of groups) {
            try {
                await axios.post(`${LARAVEL_URL}/api/bots/${botId}/sync-group`, {
                    groupId: group.id,
                    name: group.subject,
                    description: '',
                    memberCount: group.size
                }, createLaravelConfig());
                syncedCount++;
            } catch (error) {
                console.error(`❌ 同步群组 "${group.subject}" 失败: ${error.message}`);
            }
        }

        res.json({
            success: true,
            message: `成功同步 ${syncedCount} 个群组`,
            data: { groupCount: syncedCount, totalGroups: groups.length }
        });
    } catch (error) {
        console.error(`❌ 同步机器人 #${botId} 群组失败: ${error.message}`);
        res.status(500).json({ success: false, message: `同步群组失败: ${error.message}` });
    }
});

app.post('/api/bot/:botId/sync-group-users', async (req, res) => {
    const { botId } = req.params;
    const { groupId } = req.body;
    console.log(`🔄 收到同步群组用户请求 - 机器人 ID: ${botId}, 群组 ID: ${groupId}`);

    try {
        const ctx = await getOrCreateSession(botId);
        if (ctx.status !== 'open') {
            return res.status(400).json({ success: false, message: '机器人未在线' });
        }

        const gid = ensureGroupId(groupId);
        const meta = await ctx.sock.groupMetadata(gid);
        const members = (meta.participants || []).map(p => {
            const jid = jidNormalizedUser(p.id);
            return {
                jid,
                phone: jidToPhone(jid),
                admin: p.admin || null,
                isAdmin: !!p.admin
            };
        });

        let syncedCount = 0;
        for (const member of members) {
            try {
                await axios.post(`${LARAVEL_URL}/api/bots/${botId}/sync-group-user-phone`, {
                    groupId: groupId,
                    phoneNumber: member.phone,
                    isAdmin: member.isAdmin,
                    joinedAt: new Date().toISOString()
                }, createLaravelConfig());
                syncedCount++;
            } catch (error) {
                console.error(`❌ 同步成员 ${member.phone} 失败: ${error.message}`);
            }
        }

        res.json({
            success: true,
            message: `成功同步 ${syncedCount} 个用户手机号`,
            data: {
                groupName: meta.subject,
                groupId: groupId,
                syncedCount: syncedCount,
                totalMembers: members.length
            }
        });
    } catch (error) {
        console.error(`❌ 同步机器人 #${botId} 群组用户失败: ${error.message}`);
        res.status(500).json({ success: false, message: `同步群组用户失败: ${error.message}` });
    }
});

// 测试网络连接
async function testNetworkConnection() {
    try {
        console.log('🌐 测试网络连接...');
        const response = await axios.get('https://web.whatsapp.com', { timeout: 10000 });
        console.log('✅ WhatsApp Web 可访问');
        return true;
    } catch (error) {
        console.error('❌ 网络连接测试失败:', error.message);
        console.log('💡 请检查网络连接或防火墙设置');
        return false;
    }
}

// 启动服务器
app.listen(PORT, async () => {
    console.log('========================================');
    console.log(`🚀 WhatsApp 机器人服务器运行中`);
    console.log(`📡 端口: ${PORT}`);
    console.log(`🌐 API: http://localhost:${PORT}`);
    console.log('========================================');

    // 测试网络连接
    await testNetworkConnection();

    // 恢复现有会话
    console.log('🔄 检查现有会话...');
    if (fs.existsSync(SESSIONS_DIR)) {
        const sessionDirs = fs.readdirSync(SESSIONS_DIR);
        for (const sessionDir of sessionDirs) {
            if (fs.statSync(path.join(SESSIONS_DIR, sessionDir)).isDirectory()) {
                console.log(`🔄 发现现有会话: ${sessionDir}`);
                try {
                    await getOrCreateSession(sessionDir);
                } catch (error) {
                    console.error(`❌ 恢复会话 ${sessionDir} 失败: ${error.message}`);
                }
            }
        }
    }
    console.log('✅ 会话恢复完成');
});

// 优雅关闭
process.on('SIGINT', async () => {
    console.log('\n🛑 正在关闭所有会话...');
    for (const [sessionId, ctx] of sessions.entries()) {
        try {
            await ctx.sock.logout();
            console.log(`✅ 会话 #${sessionId} 已停止`);
        } catch (error) {
            console.error(`❌ 停止会话 #${sessionId} 时出错: ${error.message}`);
        }
    }
    console.log('✅ 所有会话已停止，正在退出...');
    process.exit(0);
});