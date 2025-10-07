#!/usr/bin/env node
/**
 * 清理过期的 WhatsApp 会话文件
 * 用法: node cleanup-sessions.js [session-id]
 * 不带参数则清理所有会话
 */

const fs = require('fs');
const path = require('path');
const readline = require('readline');

const SESSIONS_DIR = path.join(__dirname, 'sessions');

const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

function askQuestion(question) {
    return new Promise(resolve => {
        rl.question(question, answer => {
            resolve(answer);
        });
    });
}

async function deleteSessions(sessionIds) {
    console.log(`\n🗑️  准备删除 ${sessionIds.length} 个会话文件...`);
    
    for (const sessionId of sessionIds) {
        const sessionPath = path.join(SESSIONS_DIR, sessionId);
        try {
            fs.rmSync(sessionPath, { recursive: true, force: true });
            console.log(`✅ 已删除: ${sessionId}`);
        } catch (error) {
            console.error(`❌ 删除失败 ${sessionId}: ${error.message}`);
        }
    }
    
    console.log('\n✅ 清理完成！');
}

async function main() {
    const targetSession = process.argv[2];
    
    if (!fs.existsSync(SESSIONS_DIR)) {
        console.log('❌ 会话目录不存在');
        process.exit(1);
    }
    
    const sessionDirs = fs.readdirSync(SESSIONS_DIR)
        .filter(name => fs.statSync(path.join(SESSIONS_DIR, name)).isDirectory());
    
    if (sessionDirs.length === 0) {
        console.log('✅ 没有需要清理的会话');
        rl.close();
        return;
    }
    
    console.log('📁 现有会话:');
    sessionDirs.forEach((dir, i) => {
        console.log(`  ${i + 1}. ${dir}`);
    });
    
    let sessionsToDelete = [];
    
    if (targetSession) {
        // 删除指定会话
        if (sessionDirs.includes(targetSession)) {
            sessionsToDelete = [targetSession];
        } else {
            console.log(`❌ 会话 "${targetSession}" 不存在`);
            rl.close();
            return;
        }
    } else {
        // 确认删除所有会话
        const answer = await askQuestion(`\n⚠️  确定要删除所有 ${sessionDirs.length} 个会话吗？(yes/no): `);
        if (answer.toLowerCase() !== 'yes') {
            console.log('❌ 已取消');
            rl.close();
            return;
        }
        sessionsToDelete = sessionDirs;
    }
    
    await deleteSessions(sessionsToDelete);
    rl.close();
}

main().catch(error => {
    console.error('❌ 错误:', error.message);
    rl.close();
    process.exit(1);
});

