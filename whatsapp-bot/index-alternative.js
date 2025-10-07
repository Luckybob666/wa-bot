const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const { io } = require('socket.io-client');
const axios = require('axios');
const config = require('./config.example');

class WhatsAppBot {
    constructor() {
        this.client = null;
        this.socketIO = null;
        this.isConnected = false;
        this.qrCode = null;
        this.botId = config.bot.id;
        this.botName = config.bot.name;
        this.botPhone = config.bot.phone;
    }

    async initialize() {
        try {
            console.log(`🤖 初始化机器人: ${this.botName} (ID: ${this.botId})`);
            
            // 连接 WebSocket
            await this.connectWebSocket();
            
            // 初始化 WhatsApp 客户端
            await this.initializeWhatsApp();
            
        } catch (error) {
            console.error('❌ 机器人初始化失败:', error);
            await this.updateBotStatus('error', error.message);
        }
    }

    async connectWebSocket() {
        return new Promise((resolve, reject) => {
            this.socketIO = io(config.websocket.url + config.websocket.namespace, {
                transports: ['websocket'],
                query: {
                    botId: this.botId
                }
            });

            this.socketIO.on('connect', () => {
                console.log('🔌 WebSocket 连接成功');
                resolve();
            });

            this.socketIO.on('disconnect', () => {
                console.log('🔌 WebSocket 连接断开');
            });

            this.socketIO.on('connect_error', (error) => {
                console.error('❌ WebSocket 连接错误:', error);
                reject(error);
            });

            // 监听来自 Laravel 的命令
            this.socketIO.on('start_bot', () => {
                console.log('📱 收到启动机器人命令');
                this.startConnection();
            });

            this.socketIO.on('stop_bot', () => {
                console.log('🛑 收到停止机器人命令');
                this.stopConnection();
            });
        });
    }

    async initializeWhatsApp() {
        try {
            this.client = new Client({
                authStrategy: new LocalAuth({
                    clientId: `bot_${this.botId}`
                }),
                puppeteer: {
                    headless: true,
                    args: [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-accelerated-2d-canvas',
                        '--no-first-run',
                        '--no-zygote',
                        '--disable-gpu'
                    ]
                }
            });

            // 监听 QR 码
            this.client.on('qr', async (qr) => {
                this.qrCode = qr;
                console.log('📱 生成 QR 码');
                qrcode.generate(qr, { small: true });
                await this.sendQRCode(qr);
                await this.updateBotStatus('connecting', '等待扫码登录');
            });

            // 监听就绪事件
            this.client.on('ready', async () => {
                console.log('✅ WhatsApp 客户端就绪');
                this.isConnected = true;
                await this.updateBotStatus('online', '已连接');
                await this.updateLastSeen();
                
                // 开始监听群组事件
                this.setupGroupEventListeners();
            });

            // 监听断开连接
            this.client.on('disconnected', async (reason) => {
                console.log('🔌 WhatsApp 客户端断开连接:', reason);
                this.isConnected = false;
                await this.updateBotStatus('offline', '连接断开');
            });

            // 监听认证失败
            this.client.on('auth_failure', async (msg) => {
                console.error('❌ 认证失败:', msg);
                await this.updateBotStatus('error', '认证失败');
            });

        } catch (error) {
            console.error('❌ WhatsApp 初始化失败:', error);
            await this.updateBotStatus('error', error.message);
        }
    }

    setupGroupEventListeners() {
        // 监听群组更新事件
        this.client.on('group_join', async (notification) => {
            console.log('👥 用户加入群组:', notification);
            await this.handleGroupParticipantsUpdate('add', notification);
        });

        this.client.on('group_leave', async (notification) => {
            console.log('👥 用户离开群组:', notification);
            await this.handleGroupParticipantsUpdate('remove', notification);
        });

        // 监听群组信息更新
        this.client.on('group_update', async (notification) => {
            console.log('📊 群组信息更新:', notification);
            await this.handleGroupUpdate(notification);
        });
    }

    async handleGroupUpdate(notification) {
        try {
            const groupId = notification.id._serialized;
            const groupName = notification.subject;
            
            // 发送群组更新事件到 Laravel
            await this.sendGroupEvent('group_updated', {
                groupId,
                groupName,
                update: notification
            });
            
        } catch (error) {
            console.error('❌ 处理群组更新失败:', error);
        }
    }

    async handleGroupParticipantsUpdate(action, notification) {
        try {
            const groupId = notification.id._serialized;
            const participants = notification.participants;
            
            for (const participant of participants) {
                const eventType = action === 'add' ? 'member_joined' : 'member_left';
                
                await this.sendGroupEvent(eventType, {
                    groupId,
                    participant: participant._serialized,
                    action
                });
            }
            
        } catch (error) {
            console.error('❌ 处理群组成员变化失败:', error);
        }
    }

    async sendQRCode(qr) {
        if (this.socketIO) {
            this.socketIO.emit('qr_code', {
                botId: this.botId,
                qr: qr
            });
        }
    }

    async updateBotStatus(status, message = '') {
        try {
            await axios.post(`${config.laravel.url}/api/bots/${this.botId}/status`, {
                status: status,
                message: message
            }, {
                headers: {
                    'Authorization': `Bearer ${config.laravel.apiToken}`,
                    'Content-Type': 'application/json'
                }
            });
            
            console.log(`📊 更新机器人状态: ${status} - ${message}`);
            
        } catch (error) {
            console.error('❌ 更新机器人状态失败:', error);
        }
    }

    async updateLastSeen() {
        try {
            await axios.post(`${config.laravel.url}/api/bots/${this.botId}/last-seen`, {}, {
                headers: {
                    'Authorization': `Bearer ${config.laravel.apiToken}`,
                    'Content-Type': 'application/json'
                }
            });
            
        } catch (error) {
            console.error('❌ 更新最后活跃时间失败:', error);
        }
    }

    async sendGroupEvent(eventType, data) {
        try {
            await axios.post(`${config.laravel.url}/api/group-events`, {
                botId: this.botId,
                eventType: eventType,
                data: data
            }, {
                headers: {
                    'Authorization': `Bearer ${config.laravel.apiToken}`,
                    'Content-Type': 'application/json'
                }
            });
            
            console.log(`📝 发送群组事件: ${eventType}`);
            
        } catch (error) {
            console.error('❌ 发送群组事件失败:', error);
        }
    }

    async startConnection() {
        if (!this.isConnected && this.client) {
            await this.client.initialize();
        }
    }

    async stopConnection() {
        if (this.client) {
            await this.client.destroy();
            this.isConnected = false;
            await this.updateBotStatus('offline', '已停止');
        }
    }
}

// 启动机器人
const bot = new WhatsAppBot();
bot.initialize().catch(console.error);

// 优雅关闭
process.on('SIGINT', async () => {
    console.log('🛑 正在关闭机器人...');
    await bot.stopConnection();
    process.exit(0);
});
