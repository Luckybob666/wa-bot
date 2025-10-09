require('dotenv').config();
const express = require('express');
const cors = require('cors');
const fs = require('fs').promises;
const fsSync = require('fs');
const path = require('path');
const NodeCache = require('node-cache');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    jidNormalizedUser,
    Browsers
} = require('@whiskeysockets/baileys');
const qrcode = require('qrcode');
const axios = require('axios');

// ==================== 配置 ====================
const config = {
    port: process.env.PORT || 3000,
    laravelUrl: process.env.LARAVEL_URL || 'http://localhost:89',
    sessionsDir: path.join(__dirname, 'sessions')
};

const app = express();
const sessions = new Map();

// 群组缓存 - 5分钟过期
const groupCache = new NodeCache({ stdTTL: 5 * 60, useClones: false });

app.use(cors());
app.use(express.json());

// 确保会话目录存在
if (!fsSync.existsSync(config.sessionsDir)) {
    fsSync.mkdirSync(config.sessionsDir, { recursive: true });
}

// ==================== 工具函数 ====================
const utils = {
    ensureGroupId: (gid) => gid.endsWith('@g.us') ? gid : `${gid}@g.us`,
    
    jidToPhone: (jid) => {
        if (!jid || !jid.includes('@s.whatsapp.net')) return null;
        const left = String(jid).split('@')[0];
        const noDevice = left.split(':')[0];
        const digits = noDevice.replace(/\D/g, '');
        return (digits.length >= 7 && digits.length <= 15) ? digits : null;
    },
    
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

// ==================== Laravel API ====================
const laravel = {
    async request(endpoint, data) {
        try {
            const response = await axios.post(`${config.laravelUrl}${endpoint}`, data, {
                timeout: 15000,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            
            // 检查响应状态
            if (response.data && response.data.success === false) {
                console.error(`❌ Laravel API 业务逻辑错误 [${endpoint}]: ${response.data.message}`);
                return false;
            }
            
            return true;
        } catch (error) {
            if (error.response) {
                // 服务器响应了错误状态码
                console.error(`❌ Laravel API 失败 [${endpoint}]: ${error.response.status} - ${error.response.data?.message || error.message}`);
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
            whatsappUserId: member.whatsappUserId, // 总是有值
            jid: member.jid,
            isAdmin: member.isAdmin,
            joinedAt: new Date().toISOString()
        });
    }
};

// ==================== WhatsApp 连接管理 ====================
class WhatsAppSession {
    constructor(sessionId, loginType = 'qr', phoneNumber = null) {
        this.sessionId = sessionId;
        this.loginType = loginType; // 'qr' 或 'sms'
        this.sock = null;
        this.status = 'connecting';
        this.lastQR = null;
        this.phoneNumber = phoneNumber;
        this.pairingCode = null;
        this.pairingCodeRequested = false;
    }

    async create() {
        if (sessions.has(this.sessionId)) {
            return sessions.get(this.sessionId);
        }

        console.log(`🤖 创建会话 #${this.sessionId} (${this.loginType})`);
        
        const sessionPath = path.join(config.sessionsDir, this.sessionId);
        const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
        const { version } = await fetchLatestBaileysVersion();

        // 创建一个简单的静默 logger 对象
        const silentLogger = {
            level: 'silent',
            fatal: () => {},
            error: () => {},
            warn: () => {},
            info: () => {},
            debug: () => {},
            trace: () => {},
            child: () => silentLogger
        };

        const socketConfig = {
            version,
            auth: state,
            logger: silentLogger, // 禁用 Baileys 内部日志
            browser: Browsers.ubuntu('WhatsApp Bot'),
            connectTimeoutMs: 60000,
            keepAliveIntervalMs: 30000,
            markOnlineOnConnect: true,
            syncFullHistory: false,
            fireInitQueries: true,
            emitOwnEvents: false,
            generateHighQualityLinkPreview: false,
            cachedGroupMetadata: async (jid) => groupCache.get(jid)
        };

        this.sock = makeWASocket(socketConfig);
        
        // 监听凭据更新
        this.sock.ev.on('creds.update', saveCreds);
        
        // 监听连接状态
        this.sock.ev.on('connection.update', (update) => this.handleConnectionUpdate(update));
        
        // 监听群组更新事件
        this.sock.ev.on('groups.update', async ([event]) => {
            try {
                const metadata = await this.sock.groupMetadata(event.id);
                groupCache.set(event.id, metadata);
            } catch (error) {
                console.error(`❌ 更新群组缓存失败: ${error.message}`);
            }
        });
        
        this.sock.ev.on('group-participants.update', async (event) => {
            try {
                const metadata = await this.sock.groupMetadata(event.id);
                groupCache.set(event.id, metadata);
            } catch (error) {
                console.error(`❌ 更新群组参与者缓存失败: ${error.message}`);
            }
        });
        
        sessions.set(this.sessionId, this);
        return this;
    }

    async handleConnectionUpdate(update) {
        const { connection, qr, lastDisconnect } = update;

        console.log(`📊 机器人 #${this.sessionId} 连接: ${connection || 'unknown'}`);

        // 处理验证码登录：在连接建立后请求配对码
        if (connection === 'connecting' && this.loginType === 'sms' && this.phoneNumber && !this.pairingCodeRequested) {
            try {
                // 等待一下确保 socket 完全准备好
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                if (!this.sock.authState.creds.registered) {
                    console.log(`📱 请求配对码: ${this.phoneNumber}`);
                    this.pairingCode = await this.sock.requestPairingCode(this.phoneNumber);
                    this.pairingCodeRequested = true;
                    console.log(`🔑 配对码: ${this.pairingCode}`);
                    
                    // 发送配对码到 Laravel
                    await laravel.sendPairingCode(this.sessionId, this.pairingCode, this.phoneNumber);
                    await laravel.updateStatus(this.sessionId, 'connecting', this.phoneNumber, '等待输入配对码');
                } else {
                    console.log(`✅ 机器人 #${this.sessionId} 已注册，无需配对码`);
                }
            } catch (error) {
                console.error(`❌ 获取配对码失败: ${error.message}`);
                // 配对码失败时，切换到 QR 码登录
                this.loginType = 'qr';
            }
        }

        if (qr && this.loginType === 'qr') {
            // 避免重复发送相同的QR码，增加时间间隔控制
            // WhatsApp QR码通常20-60秒才更新一次，设置18秒间隔
            const now = Date.now();
            const QR_UPDATE_INTERVAL = 18000; // 18秒
            
            if (!this.lastQR || (now - (this.lastQRSendTime || 0)) > QR_UPDATE_INTERVAL) {
                console.log(`📱 机器人 #${this.sessionId} 生成 QR 码`);
                try {
                    const qrImage = await qrcode.toDataURL(qr);
                    
                    // 只有QR码真的变化了才发送
                    if (this.lastQR !== qrImage) {
                        this.lastQR = qrImage;
                        this.lastQRSendTime = now;
                        
                        console.log(`📤 发送 QR 码到 Laravel...`);
                        const qrSent = await laravel.sendQrCode(this.sessionId, this.lastQR);
                        if (qrSent) {
                            console.log(`✅ 机器人 #${this.sessionId} QR 码已发送到 Laravel`);
                        } else {
                            console.error(`❌ 机器人 #${this.sessionId} QR 码发送到 Laravel 失败`);
                        }
                        await laravel.updateStatus(this.sessionId, 'connecting', null, '等待扫码登录');
                    } else {
                        console.log(`📱 机器人 #${this.sessionId} QR 码内容未变化，跳过发送`);
                    }
                } catch (error) {
                    console.error(`❌ QR 码处理失败: ${error.message}`);
                }
            } else {
                const remainingTime = Math.ceil((QR_UPDATE_INTERVAL - (now - this.lastQRSendTime)) / 1000);
                console.log(`📱 机器人 #${this.sessionId} QR 码发送间隔未到，还需等待 ${remainingTime} 秒`);
            }
        }

        if (connection === 'open') {
            this.status = 'open';
            this.lastQR = null;
            this.pairingCode = null;
            this.phoneNumber = this.sock.user.id.split(':')[0];
            const pushname = this.sock.user.name || '未设置';
            
            console.log(`✅ 机器人 #${this.sessionId} 上线！手机号: ${this.phoneNumber}, 昵称: ${pushname}`);
            
            // 发送状态更新到 Laravel
            console.log(`📤 发送状态更新到 Laravel: online`);
            const statusUpdated = await laravel.updateStatus(this.sessionId, 'online', this.phoneNumber, '连接成功');
            if (statusUpdated) {
                console.log(`✅ 机器人 #${this.sessionId} 状态已更新到 Laravel: online`);
            } else {
                console.error(`❌ 机器人 #${this.sessionId} 状态更新到 Laravel 失败`);
            }
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const isLoggedOut = statusCode === DisconnectReason.loggedOut;
            
            console.log(`❌ 机器人 #${this.sessionId} 断开 [${statusCode || 'unknown'}]`);
            
            if (isLoggedOut) {
                console.log(`🔑 机器人 #${this.sessionId} 会话已过期`);
                this.status = 'close';
                sessions.delete(this.sessionId);
                await utils.deleteSessionFiles(this.sessionId);
                await laravel.updateStatus(this.sessionId, 'offline', null, '会话已过期，请重新登录');
            } else if (statusCode === 515 || statusCode === 428) {
                // 515和428是配对成功信号，需要快速重连
                console.log(`✅ 机器人 #${this.sessionId} 配对成功，立即重连...`);
                await laravel.updateStatus(this.sessionId, 'connecting', null, '配对成功，正在连接...');
                sessions.delete(this.sessionId);
                // 立即重连，不需要等待
                setTimeout(() => new WhatsAppSession(this.sessionId, this.loginType).create(), 1000);
            } else {
                console.log(`🔄 机器人 #${this.sessionId} 5秒后重连`);
                this.status = 'close';
                await laravel.updateStatus(this.sessionId, 'offline', null, '连接断开，重连中...');
                sessions.delete(this.sessionId);
                setTimeout(() => new WhatsAppSession(this.sessionId, this.loginType).create(), 5000);
            }
        }
    }

    async requestPairingCode(phoneNumber) {
        if (!this.sock) {
            throw new Error('Socket 未初始化');
        }
        
        // 检查是否已注册
        if (!this.sock.authState.creds.registered) {
            console.log(`📱 请求配对码: ${phoneNumber}`);
            try {
                const code = await this.sock.requestPairingCode(phoneNumber);
                console.log(`🔑 配对码: ${code}`);
                await laravel.updateStatus(this.sessionId, 'connecting', phoneNumber, `配对码: ${code}`);
                return code;
            } catch (error) {
                console.error(`❌ 获取配对码失败: ${error.message}`);
                throw error;
            }
        } else {
            console.log(`✅ 机器人 #${this.sessionId} 已注册，无需配对码`);
            return null;
        }
    }

    async stop(deleteFiles = false) {
        if (this.sock) {
            try {
                if (deleteFiles) {
                    // 完全登出，删除会话文件
                    await this.sock.logout();
                } else {
                    // 只断开连接，保持会话状态挂起
                    this.sock.ws.close();
                }
            } catch (error) {
                console.error(`❌ 断开连接失败: ${error.message}`);
            }
        }
        
        if (deleteFiles) {
            await utils.deleteSessionFiles(this.sessionId);
            console.log(`✅ 会话 #${this.sessionId} 已完全停止（会话文件已删除）`);
        } else {
            console.log(`✅ 会话 #${this.sessionId} 已断开连接（会话状态已挂起）`);
        }
        
        sessions.delete(this.sessionId);
    }
}

// ==================== API 路由 ====================

// 健康检查
app.get('/', (req, res) => {
    res.json({
        success: true,
        message: 'WhatsApp 机器人服务器运行中',
        version: '3.0.0',
        sessions: sessions.size
    });
});

// 启动机器人（二维码登录）
app.post('/api/bot/:botId/start', async (req, res) => {
    try {
        const { botId } = req.params;
        console.log(`📥 启动请求 - 机器人 #${botId} (二维码登录)`);
        
        if (sessions.has(botId)) {
            const session = sessions.get(botId);
            return res.json({ 
                success: true, 
                message: `机器人已运行，状态: ${session.status}`,
                data: { botId, status: session.status }
            });
        }
        
        const session = new WhatsAppSession(botId, 'qr');
        await session.create();
        res.json({ success: true, message: '机器人启动中', data: { botId, status: 'connecting' } });
    } catch (error) {
        console.error(`❌ 启动失败: ${error.message}`);
        res.status(500).json({ success: false, message: error.message });
    }
});

// 启动机器人（验证码登录）
app.post('/api/bot/:botId/start-sms', async (req, res) => {
    try {
        const { botId } = req.params;
        const { phoneNumber } = req.body;
        
        if (!phoneNumber) {
            return res.status(400).json({ success: false, message: '手机号不能为空' });
        }
        
        console.log(`📥 启动请求 - 机器人 #${botId} (验证码登录: ${phoneNumber})`);
        
        if (sessions.has(botId)) {
            const session = sessions.get(botId);
            return res.json({ 
                success: true, 
                message: `机器人已运行，状态: ${session.status}`,
                data: { botId, status: session.status }
            });
        }
        
        const session = new WhatsAppSession(botId, 'sms', phoneNumber);
        await session.create();
        
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
app.post('/api/bot/:botId/stop', async (req, res) => {
    try {
        const { botId } = req.params;
        const { deleteFiles = false } = req.body; // 默认不删除会话文件
        console.log(`🛑 停止请求 - 机器人 #${botId} (删除文件: ${deleteFiles})`);
        
        const session = sessions.get(botId);
        if (session) {
            await session.stop(deleteFiles);
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
app.get('/api/bot/:botId', (req, res) => {
    try {
        const { botId } = req.params;
        const session = sessions.get(botId);
        
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
app.post('/api/bot/:botId/sync-groups', async (req, res) => {
    try {
        const { botId } = req.params;
        console.log(`🔄 同步群组 - 机器人 #${botId}`);
        
        const session = sessions.get(botId);
        if (!session || session.status !== 'open') {
            return res.status(409).json({ success: false, message: '机器人未在线' });
        }

        const groupsDict = await session.sock.groupFetchAllParticipating();
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
        
        const session = sessions.get(botId);
        if (!session || session.status !== 'open') {
            return res.status(409).json({ success: false, message: '机器人未在线' });
        }

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
app.get('/sessions', (req, res) => {
    const list = Array.from(sessions.entries()).map(([id, session]) => ({
        sessionId: id,
        status: session.status || 'connecting',
        hasQR: !!session.lastQR,
        phoneNumber: session.phoneNumber
    }));
    res.json({ success: true, data: { total: sessions.size, sessions: list } });
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

    // 恢复现有会话
    console.log('🔄 检查现有会话...');
    if (fsSync.existsSync(config.sessionsDir)) {
        const sessionDirs = await fs.readdir(config.sessionsDir);
        for (const sessionDir of sessionDirs) {
            const sessionPath = path.join(config.sessionsDir, sessionDir);
            const stat = await fs.stat(sessionPath);
            
            if (stat.isDirectory()) {
                console.log(`🔄 发现会话: ${sessionDir}，尝试恢复...`);
                try {
                    // 尝试恢复会话，使用现有的认证状态
                    const session = new WhatsAppSession(sessionDir, 'qr');
                    await session.create();
                    console.log(`✅ 会话 ${sessionDir} 恢复成功`);
                } catch (error) {
                    console.error(`❌ 恢复会话 ${sessionDir} 失败: ${error.message}`);
                    // 如果恢复失败，可能是会话已过期，清理文件
                    try {
                        await utils.deleteSessionFiles(sessionDir);
                        console.log(`🗑️ 已清理过期的会话文件: ${sessionDir}`);
                    } catch (cleanupError) {
                        console.error(`❌ 清理会话文件失败: ${cleanupError.message}`);
                    }
                }
            }
        }
    }
    console.log('✅ 会话恢复完成');
});

// 优雅关闭
process.on('SIGINT', async () => {
    console.log('\n🛑 正在关闭所有会话...');
    for (const [sessionId, session] of sessions.entries()) {
        if (session.sock) {
            try {
                // 只断开连接，不登出，保持会话状态挂起
                session.sock.ws.close();
                console.log(`📱 会话 #${sessionId} 已断开连接（会话状态已挂起）`);
            } catch (error) {
                console.error(`❌ 断开会话 #${sessionId} 失败: ${error.message}`);
            }
        }
        sessions.delete(sessionId);
    }
    console.log('✅ 服务已停止，会话状态已挂起');
    process.exit(0);
});