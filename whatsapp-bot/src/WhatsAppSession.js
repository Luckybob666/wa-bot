import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    Browsers
} from '@whiskeysockets/baileys';
import qrcode from 'qrcode';
import path from 'path';
import { config } from './config.js';
import { utils } from './utils.js';
import { laravel } from './laravel.js';
import NodeCache from 'node-cache';

// 群组缓存 - 5分钟过期
const groupCache = new NodeCache({ stdTTL: 5 * 60, useClones: false });

export class WhatsAppSession {
    constructor(sessionId, loginType = 'qr', phoneNumber = null, isBotDeletedChecker = null, reconnectCallback = null) {
        this.sessionId = sessionId;
        this.loginType = loginType; // 'qr' 或 'sms'
        this.sock = null;
        this.status = 'connecting';
        this.lastQR = null;
        this.phoneNumber = phoneNumber;
        this.pairingCode = null;
        this.pairingCodeRequested = false;
        this.isBotDeletedChecker = isBotDeletedChecker;
        this.reconnectCallback = reconnectCallback; // 重连回调函数
        // 记录机器人自身账号信息（JID / LID / 基础 ID），用于识别机器人是否被移除群组
        this.botJid = null;
        this.botLid = null;
        this.botBaseId = null;
    }

    async create() {
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
            // 必须开启 emitOwnEvents，才能收到「自己创建群 / 自己被拉入群」等事件
            emitOwnEvents: true,
            generateHighQualityLinkPreview: false,
            cachedGroupMetadata: async (jid) => groupCache.get(jid)
        };

        this.sock = makeWASocket(socketConfig);
        
        // 监听凭据更新
        this.sock.ev.on('creds.update', saveCreds);
        
        // 监听连接状态
        this.sock.ev.on('connection.update', (update) => this.handleConnectionUpdate(update));
        
        // 监听群组更新事件（群信息变化、机器人加入/退出群组）
        this.sock.ev.on('groups.update', async (events) => {
            try {
                for (const event of events) {
                    const groupId = event.id;
                    
                    try {
                        // 更新缓存
                        const metadata = await this.sock.groupMetadata(groupId);
                        groupCache.set(groupId, metadata);
                        
                        // 确保群组在数据库中存在（可能是新群组或机器人刚加入的群组）
                        try {
                            await laravel.groupJoined(this.sessionId, groupId, metadata.subject || groupId, metadata.size || 0);
                            console.log(`✅ 群组更新事件 - 群组 ${metadata.subject || groupId} (${groupId}) 已确保存在`);
                            
                            // 同步群组的所有成员（确保用户信息已存在）
                            try {
                                const gid = utils.ensureGroupId(groupId);
                                const members = (metadata.participants || []).map(p => {
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
                                    await laravel.syncMembers(this.sessionId, groupId, members);
                                    console.log(`✅ 群组 ${metadata.subject || groupId} 同步了 ${members.length} 个成员`);
                                }
                            } catch (error) {
                                console.error(`❌ 同步群组成员失败 [${groupId}]: ${error.message}`);
                            }
                        } catch (error) {
                            console.error(`❌ 确保群组存在失败 [${groupId}]: ${error.message}`);
                        }
                    } catch (error) {
                        // 如果获取群组元数据失败，可能是机器人被移除了
                        const msg = error?.message || '';
                        if (msg.includes('not-authorized') || msg.includes('forbidden')) {
                            console.log(`⚠️ 机器人可能已被移除群组: ${groupId}`);
                            try {
                                await laravel.groupLeft(this.sessionId, groupId);
                                console.log(`✅ 已标记群组 ${groupId} 为已退出状态`);
                            } catch (err) {
                                console.error(`❌ 标记群组退出失败 [${groupId}]: ${err.message}`);
                            }
                        } else {
                            console.error(`❌ 更新群组缓存失败 [${groupId}]: ${error.message}`);
                        }
                    }
                }
            } catch (error) {
                console.error(`❌ 处理群组更新事件失败: ${error.message}`);
            }
        });
        
        // 监听群成员变化事件
        this.sock.ev.on('group-participants.update', async (event) => {
            try {
                const groupId = event.id;
                const action = event.action; // 'add' 或 'remove'
                const participants = event.participants || [];
                const author = event.author; // 操作者（如果是被移除，author 是管理员）
                
                // 获取群组元数据（包含完整的成员信息）
                let metadata = null;
                try {
                    metadata = await this.sock.groupMetadata(groupId);
                    groupCache.set(groupId, metadata);
                } catch (error) {
                    const msg = error?.message || '';
                    // 如果这里拿 metadata 失败，多数情况是机器人已经没有该群的访问权限（被移除或退群）
                    if (msg.includes('not-authorized') || msg.includes('forbidden')) {
                        console.log(`⚠️ 机器人可能已不在群 ${groupId} 中（获取群元数据失败: ${msg}）`);
                        try {
                            await laravel.groupLeft(this.sessionId, groupId);
                            console.log(`✅ 已标记群组 ${groupId} 为已退出状态`);
                        } catch (err) {
                            console.error(`❌ 标记群组退出失败 [${groupId}]: ${err.message}`);
                        }
                    } else {
                        console.error(`❌ 更新群组缓存失败: ${error.message}`);
                    }
                }
                // 处理成员变化
                for (const participantId of participants) {
                    try {
                        // 检查是否是机器人自己被移除
                        // 优先使用登录时记录下来的 botJid / botLid / botBaseId，避免 this.sock.user 为空导致报错
                        const botJid = this.botJid || (this.sock.user && this.sock.user.id) || null;
                        const botLid = this.botLid || (this.sock.user && this.sock.user.lid) || null;
                        const botBaseId = this.botBaseId || (botJid ? botJid.split(':')[0].split('@')[0] : null);

                        const participantClean = participantId.split(':')[0];
                        const participantBaseId = participantClean.split('@')[0];

                        // 检查机器人是否被移除（支持 JID / LID / 纯 ID 多种匹配方式）
                        if (
                            action === 'remove' &&
                            (
                                (botJid && participantId === botJid) ||          // 完整 JID 相等
                                (botLid && participantId === botLid) ||          // 完整 LID 相等
                                (botBaseId && participantBaseId === botBaseId) ||// 基础 ID 相等（号码部分）
                                (botBaseId && participantId.includes(botBaseId)) // participant 中包含机器人 ID
                            )
                        ) {
                            // 机器人自己被移除出群
                            console.log(`⚠️ 机器人 #${this.sessionId} 被移除出群 ${groupId}`);
                            try {
                                await laravel.groupLeft(this.sessionId, groupId);
                                console.log(`✅ 已标记群组 ${groupId} 为已退出状态`);
                            } catch (error) {
                                console.error(`❌ 标记群组退出失败 [${groupId}]: ${error.message}`);
                            }
                            continue;
                        }
                        
                        // 从元数据中查找用户信息
                        let participantInfo = null;
                        let jid = participantId; // 默认使用 participantId
                        let phone = null;
                        let isAdmin = false;
                        
                        if (metadata && metadata.participants) {
                            // 尝试通过 jid、lid 或 id 匹配
                            participantInfo = metadata.participants.find(p => 
                                p.jid === participantId || 
                                p.lid === participantId || 
                                p.id === participantId
                            );
                            
                            if (participantInfo) {
                                // 使用元数据中的 jid（包含完整手机号）
                                jid = participantInfo.jid || participantId;
                                phone = utils.jidToPhone(jid);
                                isAdmin = !!participantInfo.admin;
                            } else {
                                // 如果找不到（用户可能已经退出，不在 participants 中）
                                // 使用 participantId 作为 jid，尝试提取手机号
                                jid = participantId;
                                phone = utils.jidToPhone(participantId);
                                // 对于 LID 用户，phone 可能是 null，这是正常的
                            }
                        } else {
                            // 如果没有元数据，使用 participantId 作为 jid
                            jid = participantId;
                            phone = utils.jidToPhone(participantId);
                        }
                        
                        // 确保 jid 有值（使用 participantId 作为后备）
                        if (!jid) {
                            jid = participantId;
                        }
                        
                        // 构建成员信息
                        // whatsapp_user_id 应该存储 participants 中的 id 字段（如：148932587991082@lid）
                        // lid 应该存储 participants 中的 lid 字段（如：148932587991082@lid）
                        let whatsappUserId = participantId; // 默认使用 participantId（通常是 id 或 lid）
                        let lid = null;
                        
                        if (participantInfo) {
                            // 从 participantInfo 中获取 id 和 lid
                            whatsappUserId = participantInfo.id || participantId;
                            lid = participantInfo.lid || participantInfo.id || null;
                        } else {
                            // 如果找不到 participantInfo（用户已退出），使用 participantId
                            // participantId 可能是 id 或 lid 格式
                            whatsappUserId = participantId;
                            lid = participantId.endsWith('@lid') ? participantId : null;
                        }
                        
                        const member = {
                            jid: jid, // 可能是 null（LID 用户）
                            whatsappUserId: whatsappUserId, // participants 中的 id 字段
                            lid: lid, // participants 中的 lid 字段
                            phone: phone, // 可能是 null（LID 用户）
                            isAdmin: isAdmin
                        };
                        
                        if (action === 'add') {
                            // 成员加入
                            console.log(`➕ 用户 ${member.phone || member.whatsappUserId} 加入群 ${groupId}`);
                            
                            // 先确保群组存在，如果不存在则先创建
                            if (metadata) {
                                try {
                                    await laravel.groupJoined(this.sessionId, groupId, metadata.subject || groupId, metadata.size || 0);
                                    console.log(`✅ 群组 ${groupId} 已确保存在`);
                                } catch (error) {
                                    console.error(`❌ 确保群组存在失败 [${groupId}]: ${error.message}`);
                                }
                            }
                            
                            // 同步成员信息
                            await laravel.syncMember(this.sessionId, groupId, {
                                ...member,
                                joinedAt: new Date().toISOString()
                            });
                        } else if (action === 'remove') {
                            // 成员退出或被移除
                            // 判断是被移除还是主动退出
                            // 如果有 author 且 author !== participant，则是被管理员移除
                            const isRemovedByAdmin = author && author !== participantId;
                            
                            if (isRemovedByAdmin) {
                                // 被管理员移除
                                console.log(`🚫 用户 ${member.phone || member.whatsappUserId} 被管理员从群 ${groupId} 移除`);
                                await laravel.memberRemoved(this.sessionId, groupId, member);
                            } else {
                                // 主动退出
                                console.log(`➖ 用户 ${member.phone || member.whatsappUserId} 退出群 ${groupId}`);
                                try {
                                    const result = await laravel.memberLeft(this.sessionId, groupId, member);
                                    if (result) {
                                        console.log(`✅ 用户退出状态已同步到 Laravel: ${member.phone || member.whatsappUserId}`);
                                    } else {
                                        console.error(`❌ 用户退出状态同步失败: ${member.phone || member.whatsappUserId}`);
                                    }
                                } catch (error) {
                                    console.error(`❌ 同步用户退出状态失败: ${error.message}`);
                                }
                            }
                        }
                    } catch (error) {
                        console.error(`❌ 处理成员变化失败 [${participantId}]: ${error.message}`);
                    }
                }
            } catch (error) {
                console.error(`❌ 处理群成员变化事件失败: ${error.message}`);
            }
        });
        
        return this;
    }

    async handleConnectionUpdate(update) {
        const { connection, qr, lastDisconnect } = update;

        if (this.isBotDeletedChecker && this.isBotDeletedChecker(this.sessionId) && connection !== 'close') {
            return;
        }

        // 处理验证码登录：在连接建立后请求配对码
        if (connection === 'connecting' && this.loginType === 'sms' && this.phoneNumber && !this.pairingCodeRequested) {
            try {
                // 等待一下确保 socket 完全准备好
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                if (!this.sock.authState.creds.registered) {
                    this.pairingCode = await this.sock.requestPairingCode(this.phoneNumber);
                    this.pairingCodeRequested = true;
                    
                    // 发送配对码到 Laravel
                    await laravel.sendPairingCode(this.sessionId, this.pairingCode, this.phoneNumber);
                    await laravel.updateStatus(this.sessionId, 'connecting', this.phoneNumber, '等待输入配对码');
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
                try {
                    const qrImage = await qrcode.toDataURL(qr);
                    
                    // 只有QR码真的变化了才发送
                    if (this.lastQR !== qrImage) {
                        this.lastQR = qrImage;
                        this.lastQRSendTime = now;
                        
                        const qrSent = await laravel.sendQrCode(this.sessionId, this.lastQR);
                        if (qrSent) {
                            // no-op
                        } else {
                            console.error(`❌ 机器人 #${this.sessionId} QR 码发送到 Laravel 失败`);
                        }
                        await laravel.updateStatus(this.sessionId, 'connecting', null, '等待扫码登录');
                    }
                } catch (error) {
                    console.error(`❌ QR 码处理失败: ${error.message}`);
                }
            }
        }

        if (connection === 'open') {
            if (this.isBotDeletedChecker && this.isBotDeletedChecker(this.sessionId)) {
                await this.stop(true);
                return;
            }

            this.status = 'open';
            this.lastQR = null;
            this.pairingCode = null;
            this.phoneNumber = this.sock.user.id.split(':')[0];

            // 记录机器人自身的 JID / LID / 基础 ID，便于后续事件中识别机器人账户
            const user = this.sock.user || {};
            this.botJid = user.id || null;
            this.botLid = user.lid || null;
            this.botBaseId = this.botJid
                ? this.botJid.split(':')[0].split('@')[0]
                : null;

            // 发送状态更新到 Laravel
            const statusUpdated = await laravel.updateStatus(this.sessionId, 'online', this.phoneNumber, '连接成功');
            if (!statusUpdated) {
                console.error(`❌ 机器人 #${this.sessionId} 状态更新到 Laravel 失败`);
            }

            // 连接成功后，同步所有群组
            try {
                console.log(`🔄 机器人 #${this.sessionId} 开始同步群组...`);
                const groupsDict = await this.sock.groupFetchAllParticipating();
                const groups = Object.values(groupsDict);
                const currentGroupIds = groups.map(g => g.id); // 当前机器人所在的所有群组 ID
                
                // 同步所有群组
                for (const group of groups) {
                    try {
                        await laravel.groupJoined(
                            this.sessionId,
                            group.id,
                            group.subject || group.id,
                            group.participants?.length || 0
                        );
                    } catch (error) {
                        console.error(`❌ 同步群组失败 [${group.id}]: ${error.message}`);
                    }
                }
                
                console.log(`✅ 机器人 #${this.sessionId} 同步了 ${groups.length} 个群组`);
                
                // 同步所有群组的所有成员，并清理不在群内的用户
                try {
                    console.log(`🔄 机器人 #${this.sessionId} 开始同步所有群组成员...`);
                    let totalSynced = 0;
                    for (const group of groups) {
                        try {
                            const gid = utils.ensureGroupId(group.id);
                            const meta = await this.sock.groupMetadata(gid);
                            const members = (meta.participants || []).map(p => {
                                const jid = p.jid;
                                const phone = utils.jidToPhone(jid);
                                return {
                                    jid,
                                    whatsappUserId: p.id || (jid ? jid.split('@')[0].split(':')[0] : null), // participants 中的 id 字段
                                    lid: p.lid || p.id || null, // participants 中的 lid 字段
                                    phone,
                                    isAdmin: !!p.admin
                                };
                            });

                            // 批量同步所有成员（如果用户重新加入，会清除 left_at）
                            if (members.length > 0) {
                                const result = await laravel.syncMembers(this.sessionId, group.id, members);
                                if (result) {
                                    totalSynced += members.length;
                                }
                            }
                            
                            // 清理不在群内的用户（标记 left_at）
                            const currentMemberJids = members.map(m => m.jid);
                            await laravel.cleanupGroupUsers(this.sessionId, group.id, currentMemberJids);
                            
                            console.log(`✅ 群组 ${group.subject || group.id} 同步了 ${members.length} 个成员`);
                        } catch (error) {
                            console.error(`❌ 同步群组成员失败 [${group.id}]: ${error.message}`);
                        }
                    }
                    console.log(`✅ 机器人 #${this.sessionId} 总共同步了 ${totalSynced} 个成员`);
                } catch (error) {
                    console.error(`❌ 同步群组成员失败: ${error.message}`);
                }
                
                // 检查并更新被移除的群组状态
                try {
                    console.log(`🔄 机器人 #${this.sessionId} 开始检查被移除的群组...`);
                    await laravel.checkRemovedGroups(this.sessionId, currentGroupIds);
                    console.log(`✅ 机器人 #${this.sessionId} 检查被移除群组完成`);
                } catch (error) {
                    console.error(`❌ 检查被移除群组失败: ${error.message}`);
                }
            } catch (error) {
                console.error(`❌ 同步群组失败: ${error.message}`);
            }
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const isLoggedOut = statusCode === DisconnectReason.loggedOut;
            
            console.log(`❌ 机器人 #${this.sessionId} 断开 [${statusCode || 'unknown'}]`);
            
            if (isLoggedOut) {
                console.log(`🔑 机器人 #${this.sessionId} 会话已过期`);
                this.status = 'close';
                if (!this.isBotDeletedChecker || !this.isBotDeletedChecker(this.sessionId)) {
                    await laravel.updateStatus(this.sessionId, 'offline', null, '会话已过期，请重新登录');
                }
            } else if (statusCode === 515 || statusCode === 428) {
                // 515和428是配对成功信号，需要快速重连
                console.log(`✅ 机器人 #${this.sessionId} 配对成功，立即重连...`);
                if (this.isBotDeletedChecker && this.isBotDeletedChecker(this.sessionId)) {
                    return;
                }
                await laravel.updateStatus(this.sessionId, 'connecting', null, '配对成功，正在连接...');
                // 通过回调函数处理重连
                if (this.reconnectCallback) {
                    setTimeout(() => {
                        if (!this.isBotDeletedChecker || !this.isBotDeletedChecker(this.sessionId)) {
                            this.reconnectCallback(this.sessionId, this.loginType, 1000);
                        }
                    }, 1000);
                }
            } else {
                console.log(`🔄 机器人 #${this.sessionId} 5秒后重连`);
                this.status = 'close';
                if (!this.isBotDeletedChecker || !this.isBotDeletedChecker(this.sessionId)) {
                    await laravel.updateStatus(this.sessionId, 'offline', null, '连接断开，重连中...');
                }
                // 通过回调函数处理重连
                if (this.reconnectCallback) {
                    setTimeout(() => {
                        if (!this.isBotDeletedChecker || !this.isBotDeletedChecker(this.sessionId)) {
                            this.reconnectCallback(this.sessionId, this.loginType, 5000);
                        }
                    }, 5000);
                }
            }
        }
    }

    async requestPairingCode(phoneNumber) {
        if (!this.sock) {
            throw new Error('Socket 未初始化');
        }
        
        // 检查是否已注册
        if (!this.sock.authState.creds.registered) {
            try {
                const code = await this.sock.requestPairingCode(phoneNumber);
                await laravel.updateStatus(this.sessionId, 'connecting', phoneNumber, `配对码: ${code}`);
                return code;
            } catch (error) {
                console.error(`❌ 获取配对码失败: ${error.message}`);
                throw error;
            }
        }
        return null;
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
        }
    }
}

