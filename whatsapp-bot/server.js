import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import { promises as fs } from 'fs';
import * as fsSync from 'fs';
import path from 'path';
import axios from 'axios';
import { config } from './src/config.js';
import { utils } from './src/utils.js';
import { sessionManager } from './src/sessionManager.js';
import { laravel, setBotDeletionHandler, setIsBotDeletedChecker } from './src/laravel.js';
import { WhatsAppSession } from './src/WhatsAppSession.js';
import createRoutes from './src/routes.js';

// 初始化 Laravel API 的依赖
setBotDeletionHandler(sessionManager.handleBotDeletion.bind(sessionManager));
setIsBotDeletedChecker(sessionManager.isBotDeleted.bind(sessionManager));

// 重连回调函数
const reconnectCallback = (sessionId, loginType, delay = 5000) => {
    setTimeout(() => {
        if (sessionManager.isBotDeleted(sessionId)) {
            return;
        }
        try {
            const session = new WhatsAppSession(
                sessionId, 
                loginType, 
                null, 
                sessionManager.isBotDeleted.bind(sessionManager),
                reconnectCallback
            );
            session.create().then(() => {
                sessionManager.saveSession(sessionId, session);
            }).catch(error => {
                console.error(`❌ 重连失败 [${sessionId}]: ${error.message}`);
            });
        } catch (error) {
            console.error(`❌ 创建重连会话失败 [${sessionId}]: ${error.message}`);
        }
    }, delay);
};

const app = express();

app.use(cors());
app.use(express.json());

// 确保会话目录存在
utils.ensureSessionsDir();

// 创建并注册路由（传入重连回调）
const routes = createRoutes(reconnectCallback);
app.use('/', routes);

// ==================== 服务器启动 ====================
app.listen(config.port, async () => {
    // 测试网络连接
    try {
        await axios.get('https://web.whatsapp.com', { timeout: 10000 });
    } catch (error) {
        console.error('❌ 网络连接测试失败，请检查网络');
    }

    // 恢复现有会话
    if (fsSync.existsSync(config.sessionsDir)) {
        const sessionDirs = await fs.readdir(config.sessionsDir);
        for (const sessionDir of sessionDirs) {
            const sessionPath = path.join(config.sessionsDir, sessionDir);
            const stat = await fs.stat(sessionPath);
            
            if (stat.isDirectory()) {
                try {
                    // 尝试恢复会话，使用现有的认证状态
                    const session = new WhatsAppSession(
                        sessionDir, 
                        'qr', 
                        null, 
                        sessionManager.isBotDeleted.bind(sessionManager),
                        reconnectCallback
                    );
                    await session.create();
                    sessionManager.saveSession(sessionDir, session);
                } catch (error) {
                    console.error(`❌ 恢复会话 ${sessionDir} 失败: ${error.message}`);
                    // 如果恢复失败，可能是会话已过期，清理文件
                    try {
                        await utils.deleteSessionFiles(sessionDir);
                    } catch (cleanupError) {
                        console.error(`❌ 清理会话文件失败: ${cleanupError.message}`);
                    }
                }
            }
        }
    }
    
    console.log(`🚀 WhatsApp 机器人服务器启动在端口 ${config.port}`);
});

// 优雅关闭
process.on('SIGINT', async () => {
    const allSessions = sessionManager.getAllSessions();
    for (const [sessionId, session] of allSessions) {
        if (session.sock) {
            try {
                // 只断开连接，不登出，保持会话状态挂起
                session.sock.ws.close();
            } catch (error) {
                console.error(`❌ 断开会话 #${sessionId} 失败: ${error.message}`);
            }
        }
        sessionManager.removeSession(sessionId);
    }
    process.exit(0);
});
