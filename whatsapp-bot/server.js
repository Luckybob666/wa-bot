require('dotenv').config();
const express = require('express');
const cors = require('cors');
const pino = require('pino');
const fs = require('fs').promises;
const fsSync = require('fs');
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

// ==================== 配置 ====================
const config = {
    port: process.env.PORT || 3000,
    laravelUrl: process.env.LARAVEL_URL || 'http://localhost:89',
    sessionsDir: path.join(__dirname, 'sessions'),
    reconnectDelay: 5000,
    restartDelay: 3000
};

const logger = pino({ level: 'info' });
const app = express();
const sessions = new Map();

app.use(cors());
app.use(express.json());

// 确保会话目录存在
if (!fsSync.existsSync(config.sessionsDir)) {
    fsSync.mkdirSync(config.sessionsDir, { recursive: true });
}

// ==================== 工具函数 ====================
const utils = {
    ensureGroupId: (gid) => gid.endsWith('@g.us') ? gid : `${gid}@g.us`,
    
    // 改进的手机号提取逻辑
    jidToPhone: (jid) => {
        if (!jid) return '';
        
        // 提取 @ 符号前的部分
        const phonePart = jid.split('@')[0];
        
        // 处理包含冒号的情况（如：60123456789:16@s.whatsapp.net）
        const cleanPhone = phonePart.split(':')[0];
        
        // 验证手机号格式（应该是纯数字）
        if (!/^\d+$/.test(cleanPhone)) {
            console.warn(`⚠️  异常的手机号格式: ${jid} -> ${cleanPhone}`);
            return cleanPhone; // 仍然返回，但记录警告
        }
        
        // 检查长度是否合理（7-15位数字）
        if (cleanPhone.length < 7 || cleanPhone.length > 15) {
            console.warn(`⚠️  手机号长度异常: ${cleanPhone} (长度: ${cleanPhone.length})`);
        }
        
        return cleanPhone;
    },
    
    // 格式化手机号显示
    formatPhoneNumber: (phone) => {
        if (!phone) return '';
        
        // 移除所有非数字字符
        const digits = phone.replace(/\D/g, '');
        
        // 如果长度异常，直接返回
        if (digits.length < 7 || digits.length > 15) {
            return phone; // 返回原始值
        }
        
        // 格式化显示（添加国家代码前缀）
        if (digits.length > 10) {
            // 国际号码格式：+60 12-345 6789
            const countryCode = digits.slice(0, -10);
            const localNumber = digits.slice(-10);
            return `+${countryCode} ${localNumber.slice(0, 2)}-${localNumber.slice(2, 5)} ${localNumber.slice(5)}`;
        } else {
            // 本地号码格式：012-345 6789
            return `${digits.slice(0, 3)}-${digits.slice(3, 6)} ${digits.slice(6)}`;
        }
    },
    
    // 清理会话文件
    async deleteSessionFiles(sessionId) {
        const sessionPath = path.join(config.sessionsDir, sessionId);
        try {
            await fs.rm(sessionPath, { recursive: true, force: true });
            console.log(`🗑️  已删除会话 #${sessionId} 的文件`);
        } catch (error) {
            console.error(`❌ 删除会话文件失败: ${error.message}`);
        }
    }
};

// ==================== Laravel API 交互 ====================
const laravel = {
    async request(endpoint, data) {
        try {
            await axios.post(`${config.laravelUrl}${endpoint}`, data, {
                timeout: 15000,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            return true;
        } catch (error) {
            // 忽略 404 错误（机器人可能已被删除）
            if (error.response?.status !== 404) {
                console.error(`❌ Laravel API 请求失败 [${endpoint}]: ${error.message}`);
            }
            return false;
        }
    },
    
    updateStatus(sessionId, status, phoneNumber = null, message = null) {
        return this.request(`/api/bots/${sessionId}/status`, {
            status, phone_number: phoneNumber, message
        });
    },
    
    sendQrCode(sessionId, qrCode) {
        return this.request(`/api/bots/${sessionId}/qr-code`, { qrCode });
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
        // 格式化手机号用于存储
        const formattedPhone = utils.formatPhoneNumber(member.phone);
        
        return this.request(`/api/bots/${sessionId}/sync-group-user-phone`, {
            groupId,
            phoneNumber: member.phone, // 原始手机号
            formattedPhone, // 格式化后的手机号
            isAdmin: member.isAdmin,
            joinedAt: new Date().toISOString()
        });
    }
};

// ==================== Socket 配置 ====================
function createSocketConfig(state, version) {
    return {
        version,
        auth: state,
        printQRInTerminal: false,
        browser: ['WhatsApp Bot', 'Chrome', '10.0'],
        logger: pino({ level: 'silent' }),
        connectTimeoutMs: 60000,
        defaultQueryTimeoutMs: 60000,
        keepAliveIntervalMs: 30000,
        retryRequestDelayMs: 1000,
        maxMsgRetryCount: 3,
        markOnlineOnConnect: true,
        syncFullHistory: false,
        fireInitQueries: true,
        emitOwnEvents: false,
        generateHighQualityLinkPreview: false,
        getMessage: async () => ({ conversation: '' })
    };
}

// ==================== 连接处理 ====================
async function handleConnectionUpdate(sessionId, ctx, update) {
    const { connection, qr, lastDisconnect } = update;

    console.log(`📊 机器人 #${sessionId} 连接: ${connection || 'unknown'}`);

    // 处理二维码
    if (qr) {
        console.log(`📱 机器人 #${sessionId} 生成 QR 码`);
        try {
            ctx.lastQR = await qrcode.toDataURL(qr);
            await laravel.sendQrCode(sessionId, ctx.lastQR);
            await laravel.updateStatus(sessionId, 'connecting', null, '等待扫码登录');
        } catch (error) {
            console.error(`❌ QR 码处理失败: ${error.message}`);
        }
    }

    // 连接成功
    if (connection === 'open') {
        ctx.status = 'open';
        ctx.lastQR = null;
        const phoneNumber = ctx.sock.user.id.split(':')[0];
        const pushname = ctx.sock.user.name || '未设置';
        
        console.log(`✅ 机器人 #${sessionId} 上线！手机号: ${phoneNumber}, 昵称: ${pushname}`);
        await laravel.updateStatus(sessionId, 'online', phoneNumber, '连接成功');
    }

    // 连接断开
    if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const isLoggedOut = statusCode === DisconnectReason.loggedOut;
        
        console.log(`❌ 机器人 #${sessionId} 断开 [${statusCode || 'unknown'}]`);
        
        if (isLoggedOut) {
            // 会话过期，清理文件
            console.log(`🔑 机器人 #${sessionId} 会话已过期，需要重新登录`);
            ctx.status = 'close';
            sessions.delete(sessionId);
            await utils.deleteSessionFiles(sessionId);
            await laravel.updateStatus(sessionId, 'offline', null, '会话已过期，请重新扫码');
        } else if (statusCode === 515 || statusCode === 428) {
            // 配对成功，需要重启
            console.log(`🔄 机器人 #${sessionId} 配对成功，重启中...`);
            await laravel.updateStatus(sessionId, 'connecting', null, '配对成功，正在连接...');
            sessions.delete(sessionId);
            setTimeout(() => createSession(sessionId), config.restartDelay);
        } else {
            // 其他错误，尝试重连
            console.log(`🔄 机器人 #${sessionId} 将在 ${config.reconnectDelay/1000} 秒后重连`);
            ctx.status = 'close';
            await laravel.updateStatus(sessionId, 'offline', null, '连接断开，重连中...');
            sessions.delete(sessionId);
            setTimeout(() => createSession(sessionId), config.reconnectDelay);
        }
    }
}

// ==================== 会话管理 ====================
async function createSession(sessionId) {
    if (sessions.has(sessionId)) {
        return sessions.get(sessionId);
    }

    console.log(`🤖 创建会话 #${sessionId}`);
    
    const sessionPath = path.join(config.sessionsDir, sessionId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
    const { version } = await fetchLatestBaileysVersion();
    const sock = makeWASocket(createSocketConfig(state, version));

    const ctx = {
        sock,
        state,
        saveCreds,
        status: 'connecting',
        lastQR: null
    };
    
    sessions.set(sessionId, ctx);

    // 监听事件
    sock.ev.on('creds.update', saveCreds);
    sock.ev.on('connection.update', (update) => handleConnectionUpdate(sessionId, ctx, update));

    return ctx;
}

async function stopSession(sessionId, deleteFiles = false) {
    const ctx = sessions.get(sessionId);
    if (ctx) {
        try {
            await ctx.sock.logout();
        } catch (error) {
            console.error(`❌ 登出失败: ${error.message}`);
        }
        sessions.delete(sessionId);
        
        if (deleteFiles) {
            await utils.deleteSessionFiles(sessionId);
        }
        
        console.log(`✅ 会话 #${sessionId} 已停止`);
    }
}

function requireOnline(ctx, res) {
    if (ctx.status !== 'open') {
        res.status(409).json({ 
            success: false, 
            error: 'not_connected', 
            message: '机器人未在线' 
        });
        return false;
    }
    return true;
}

// ==================== API 路由 ====================

// 健康检查
app.get('/', (req, res) => {
    res.json({
        success: true,
        message: 'WhatsApp 机器人服务器运行中',
        version: '2.1.0',
        sessions: sessions.size
    });
});

// 列出所有会话
app.get('/sessions', (req, res) => {
    const list = Array.from(sessions.entries()).map(([id, ctx]) => ({
        sessionId: id,
        status: ctx.status || 'connecting',
        hasQR: !!ctx.lastQR
    }));
    res.json({ success: true, data: { total: sessions.size, sessions: list } });
});

// 统一的会话操作路由
app.route('/api/bot/:botId')
    .get(async (req, res) => {
        // 获取状态
        try {
            const { botId } = req.params;
            const ctx = sessions.get(botId);
            
            if (!ctx) {
                return res.json({ success: true, botId, status: 'offline', hasQR: false });
            }
            
            res.json({ 
                success: true, 
                botId, 
                status: ctx.status, 
                hasQR: !!ctx.lastQR,
                qr: ctx.lastQR
            });
        } catch (error) {
            res.status(500).json({ success: false, message: error.message });
        }
    });

// 启动机器人
app.post('/api/bot/:botId/start', async (req, res) => {
    try {
        const { botId } = req.params;
        console.log(`📥 启动请求 - 机器人 #${botId}`);
        
        if (sessions.has(botId)) {
            const ctx = sessions.get(botId);
            return res.json({ 
                success: true, 
                message: `机器人已运行，状态: ${ctx.status}`,
                data: { botId, status: ctx.status }
            });
        }
        
        await createSession(botId);
        res.json({ success: true, message: '机器人启动中', data: { botId, status: 'connecting' } });
    } catch (error) {
        console.error(`❌ 启动失败: ${error.message}`);
        res.status(500).json({ success: false, message: error.message });
    }
});

// 停止机器人
app.post('/api/bot/:botId/stop', async (req, res) => {
    try {
        const { botId } = req.params;
        const { deleteFiles } = req.body;
        console.log(`🛑 停止请求 - 机器人 #${botId}`);
        
        await stopSession(botId, deleteFiles);
        await laravel.updateStatus(botId, 'offline', null, '用户手动停止');
        
        res.json({ success: true, message: '机器人已停止' });
    } catch (error) {
        console.error(`❌ 停止失败: ${error.message}`);
        res.status(500).json({ success: false, message: error.message });
    }
});

// 同步群组
app.post('/api/bot/:botId/sync-groups', async (req, res) => {
    try {
        const { botId } = req.params;
        console.log(`🔄 同步群组 - 机器人 #${botId}`);
        
        const ctx = await createSession(botId);
        if (!requireOnline(ctx, res)) return;

        const groupsDict = await ctx.sock.groupFetchAllParticipating();
        const groups = Object.values(groupsDict).map(g => ({
            id: g.id,
            subject: g.subject,
            size: g.participants?.length || 0
        }));

        let syncedCount = 0;
        for (const group of groups) {
            if (await laravel.syncGroup(botId, group)) syncedCount++;
        }

        res.json({
            success: true,
            message: `成功同步 ${syncedCount}/${groups.length} 个群组`,
            data: { syncedCount, totalGroups: groups.length }
        });
    } catch (error) {
        console.error(`❌ 同步群组失败: ${error.message}`);
        res.status(500).json({ success: false, message: error.message });
    }
});

// 同步群组用户
app.post('/api/bot/:botId/sync-group-users', async (req, res) => {
    try {
        const { botId } = req.params;
        const { groupId } = req.body;
        console.log(`🔄 同步群组用户 - 机器人 #${botId}, 群组: ${groupId}`);
        
        const ctx = await createSession(botId);
        if (!requireOnline(ctx, res)) return;

        const gid = utils.ensureGroupId(groupId);
        const meta = await ctx.sock.groupMetadata(gid);
        const members = (meta.participants || []).map(p => {
            const jid = jidNormalizedUser(p.id);
            return {
                jid,
                phone: utils.jidToPhone(jid),
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

// 兼容旧版 API（可选）
app.get('/sessions/:sessionId/qr', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const ctx = await createSession(sessionId);
        res.json({ success: true, sessionId, status: ctx.status, qr: ctx.lastQR });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/sessions/:sessionId/status', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const ctx = await createSession(sessionId);
        res.json({ success: true, sessionId, status: ctx.status, hasQR: !!ctx.lastQR });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// ==================== 服务器启动 ====================
app.listen(config.port, async () => {
    console.log('========================================');
    console.log(`🚀 WhatsApp 机器人服务器运行中`);
    console.log(`📡 端口: ${config.port}`);
    console.log(`🌐 API: http://localhost:${config.port}`);
    console.log('========================================');

    // 测试网络连接
    try {
        await axios.get('https://web.whatsapp.com', { timeout: 10000 });
        console.log('✅ WhatsApp Web 可访问');
    } catch (error) {
        console.error('❌ 网络连接测试失败，请检查网络');
    }

    // 恢复现有会话（仅当凭据有效时）
    console.log('🔄 检查现有会话...');
    if (fsSync.existsSync(config.sessionsDir)) {
        const sessionDirs = await fs.readdir(config.sessionsDir);
        for (const sessionDir of sessionDirs) {
            const sessionPath = path.join(config.sessionsDir, sessionDir);
            const stat = await fs.stat(sessionPath);
            
            if (stat.isDirectory()) {
                console.log(`🔄 发现会话: ${sessionDir}`);
                try {
                    await createSession(sessionDir);
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
    for (const [sessionId] of sessions.entries()) {
        await stopSession(sessionId, false);
    }
    console.log('✅ 已退出');
    process.exit(0);
});
