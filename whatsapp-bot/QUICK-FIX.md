# 🔧 快速修复：错误 401 会话已过期

## 问题分析

你遇到的错误信息：
```
❌ 机器人 #1 连接断开，原因: 401，是否重连: 否
❌ 机器人 #2 连接断开，原因: 401，是否重连: 否
```

**错误 401** = `DisconnectReason.loggedOut`

**原因**：
1. WhatsApp 会话凭据已过期
2. 该 WhatsApp 账号在其他设备登录
3. 会话文件损坏或无效

## ✅ 解决方案（按顺序执行）

### 步骤 1：停止服务器

在运行 Node.js 的终端按 `Ctrl+C` 停止服务器。

### 步骤 2：清理过期会话

```bash
cd whatsapp-bot

# 清理所有会话
node cleanup-sessions.js
# 出现提示时输入: yes

# 或者只清理指定会话
node cleanup-sessions.js 1
node cleanup-sessions.js 2
```

或者手动删除：
```bash
# Windows PowerShell
Remove-Item -Recurse -Force sessions\1
Remove-Item -Recurse -Force sessions\2

# Linux/Mac
rm -rf sessions/1
rm -rf sessions/2
```

### 步骤 3：重启服务器

```bash
npm start
# 或
node server.js
```

### 步骤 4：在 Laravel 后台重新连接

1. 访问 Laravel 后台 `/admin/bots`
2. 选择机器人
3. 点击"连接"或"启动"按钮
4. 扫描新生成的二维码
5. 等待连接成功

## 🎯 预期结果

正常的连接流程应该看到：

```
🤖 创建会话 #2
📊 机器人 #2 连接: connecting
📱 机器人 #2 生成 QR 码
✅ 机器人 #2 QR 码已发送到 Laravel
[用户扫码]
📊 机器人 #2 连接: unknown
🔄 机器人 #2 配对成功，重启中...
🤖 创建会话 #2
📊 机器人 #2 连接: open
✅ 机器人 #2 上线！手机号: 60112345678, 昵称: My Name
```

## ⚠️ 关于机器人 #1 的 404 错误

```
❌ 更新机器人 #1 状态到 Laravel 失败: Request failed with status code 404
```

**原因**：机器人 #1 在 Laravel 数据库中不存在（可能已被删除）

**解决方案**：

```bash
# 清理机器人 #1 的会话文件
node cleanup-sessions.js 1
```

或者在 Laravel 后台重新创建 ID 为 1 的机器人。

## 📌 预防措施

### 避免频繁登录/登出

WhatsApp 可能会限制频繁的登录/登出操作：

- ✅ 保持服务器持续运行
- ✅ 不要频繁重启服务器
- ✅ 不要同时在多个地方登录同一账号
- ❌ 避免短时间内多次扫码

### 服务器稳定运行

使用 PM2 保持服务器运行：

```bash
# 安装 PM2
npm install -g pm2

# 启动服务器
pm2 start server.js --name whatsapp-bot

# 查看状态
pm2 status

# 查看日志
pm2 logs whatsapp-bot

# 设置开机自启
pm2 startup
pm2 save
```

### 定期检查会话状态

```bash
# 访问状态接口
curl http://localhost:3000/sessions

# 检查特定机器人
curl http://localhost:3000/api/bot/2
```

## 🔍 调试技巧

### 查看详细日志

在 `server.js` 中临时启用详细日志：

```javascript
logger: pino({ level: 'info' })  // 改为 'info' 或 'debug'
```

### 测试网络连接

```bash
curl https://web.whatsapp.com
# 应该返回 HTML 页面
```

### 验证 Laravel 连接

```bash
# 测试 Laravel API
curl http://localhost:89/api/bots/2/status

# 或者检查 .env 配置
cat .env | grep LARAVEL_URL
```

## 📞 仍然无法解决？

检查以下事项：

1. **Node.js 版本**
   ```bash
   node --version
   # 应该 >= 16.0.0
   ```

2. **依赖是否完整**
   ```bash
   npm install
   ```

3. **端口是否冲突**
   ```bash
   # Windows
   netstat -ano | findstr :3000
   
   # Linux/Mac
   lsof -i :3000
   ```

4. **防火墙设置**
   - 确保 3000 端口未被阻止
   - 确保可以访问 web.whatsapp.com

5. **WhatsApp 账号状态**
   - 确认手机号未被 WhatsApp 封禁
   - 确认 WhatsApp 应用为最新版本
   - 尝试在手机上正常使用 WhatsApp

## 🎉 完成

清理会话后重新扫码，应该就能正常连接了！

如果问题依然存在，请提供完整的错误日志以便进一步诊断。

