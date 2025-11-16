import { promises as fs } from 'fs';
import * as fsSync from 'fs';
import path from 'path';
import { config } from './config.js';

export const utils = {
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
        } catch (error) {
            console.error(`❌ 删除会话文件失败: ${error.message}`);
        }
    },
    
    ensureSessionsDir() {
        if (!fsSync.existsSync(config.sessionsDir)) {
            fsSync.mkdirSync(config.sessionsDir, { recursive: true });
        }
    }
};

