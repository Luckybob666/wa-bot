import { utils } from './utils.js';

// 会话管理器
const sessions = new Map();
const deletedBots = new Set();

export const sessionManager = {
    getSession(botId) {
        return sessions.get(botId);
    },
    
    saveSession(botId, session) {
        sessions.set(botId, session);
    },
    
    removeSession(botId) {
        sessions.delete(botId);
    },
    
    isBotDeleted(botId) {
        return deletedBots.has(botId);
    },
    
    markBotAsDeleted(botId) {
        deletedBots.add(botId);
    },
    
    unmarkBotAsDeleted(botId) {
        deletedBots.delete(botId);
    },
    
    getAllSessions() {
        return sessions;
    },
    
    getSessionsCount() {
        return sessions.size;
    },
    
    async handleBotDeletion(sessionId, reason = 'unknown') {
        if (!sessionId || deletedBots.has(sessionId)) {
            return;
        }

        deletedBots.add(sessionId);
        console.warn(`⚠️ Laravel 返回机器人 #${sessionId} 不存在: ${reason}`);

        const session = sessions.get(sessionId);
        if (session) {
            session.status = 'removed';
            if (session.sock) {
                try {
                    session.sock.ws?.close();
                } catch (error) {
                    console.error(`❌ 关闭会话 #${sessionId} 连接失败: ${error.message}`);
                }
            }
            sessions.delete(sessionId);
        }

        // 删除 session 文件夹
        try {
            await utils.deleteSessionFiles(sessionId);
            console.log(`🗑️ 已删除机器人 #${sessionId} 的 session 文件夹`);
        } catch (error) {
            console.error(`❌ 删除机器人 #${sessionId} 的 session 文件夹失败: ${error.message}`);
        }
    }
};

