const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');

console.log('🤖 WhatsApp 机器人测试程序');
console.log('====================================');

const client = new Client({
    authStrategy: new LocalAuth({
        clientId: 'test_bot'
    }),
    puppeteer: {
        headless: false, // 设置为 false 可以看到浏览器窗口
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox'
        ]
    }
});

// 监听 QR 码
client.on('qr', (qr) => {
    console.log('\n📱 请使用 WhatsApp 扫描以下二维码：');
    qrcode.generate(qr, { small: true });
    console.log('\n扫描步骤：');
    console.log('1. 打开 WhatsApp 应用');
    console.log('2. 点击右上角菜单 → 链接设备');
    console.log('3. 扫描上方二维码');
});

// 监听就绪事件
client.on('ready', async () => {
    console.log('\n✅ WhatsApp 客户端就绪！');
    console.log('====================================');
    
    try {
        // 获取所有群组
        const chats = await client.getChats();
        const groups = chats.filter(chat => chat.isGroup);
        
        console.log(`\n📊 找到 ${groups.length} 个群组：`);
        groups.forEach((group, index) => {
            console.log(`${index + 1}. ${group.name} (成员: ${group.participants ? group.participants.length : 'N/A'})`);
        });
        
    } catch (error) {
        console.error('❌ 获取群组失败:', error);
    }
});

// 监听认证成功
client.on('authenticated', () => {
    console.log('\n✅ 认证成功！');
});

// 监听认证失败
client.on('auth_failure', (msg) => {
    console.error('\n❌ 认证失败:', msg);
});

// 监听断开连接
client.on('disconnected', (reason) => {
    console.log('\n🔌 断开连接:', reason);
});

// 初始化客户端
console.log('\n⏳ 正在初始化 WhatsApp 客户端...');
client.initialize();

// 优雅关闭
process.on('SIGINT', async () => {
    console.log('\n\n🛑 正在关闭客户端...');
    await client.destroy();
    process.exit(0);
});
