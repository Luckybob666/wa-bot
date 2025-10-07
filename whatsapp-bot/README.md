# WhatsApp 机器人服务器 v2.1

## 🎯 架构说明

本项目采用 **Laravel 主控 + Node.js 服务** 的架构：

- **Laravel 后台**：完全控制机器人（创建、启动、停止、查看数据）
- **Node.js 服务**：提供 HTTP API，执行 WhatsApp 连接操作（基于 Baileys 库）

## ✨ 特性

- 🚀 简洁的代码结构（基于 Baileys 官方最佳实践）
- 📱 **双重登录方式**：二维码扫码 + 验证码登录
- 🔄 自动处理会话过期和重连
- 🧹 智能清理过期会话
- 🔌 支持多机器人同时运行
- 📊 实时状态同步到 Laravel
- 🛡️ 正确处理 WhatsApp LID 隐私保护机制

## 🚀 快速开始

### 1. 安装依赖

```bash
npm install
```

### 2. 配置环境变量（可选）

创建 `.env` 文件：
```env
PORT=3000
LARAVEL_URL=http://localhost:89
```

### 3. 启动服务器

```bash
# 方式1：双击启动脚本（Windows）
start.bat

# 方式2：命令行启动
npm start

# 方式3：开发模式（自动重启）
npm run dev
```

### 4. 验证服务器运行

访问 `http://localhost:3000` 应该能看到服务器状态。

### 4. 在 Laravel 后台操作

1. 访问 Laravel 后台 `/admin/bots`
2. 创建或选择机器人
3. 点击"连接"按钮
4. 在页面中扫描显示的 QR 码
5. 等待连接成功

## 📡 API 接口

### 核心接口

#### 1. 启动机器人（二维码登录）
```http
POST /api/bot/:botId/start
```

**响应**：
```json
{
  "success": true,
  "message": "机器人启动中",
  "data": {
    "botId": "1",
    "status": "connecting"
  }
}
```

#### 2. 启动机器人（验证码登录）
```http
POST /api/bot/:botId/start-sms
Content-Type: application/json

{
  "phoneNumber": "60123456789"
}
```

**响应**：
```json
{
  "success": true,
  "message": "配对码已生成",
  "data": {
    "botId": "1",
    "status": "connecting",
    "pairingCode": "123-456-789"
  }
}
```

#### 3. 停止机器人
```http
POST /api/bot/:botId/stop
Content-Type: application/json

{
  "deleteFiles": true  // 可选：是否删除会话文件
}
```

#### 4. 获取机器人状态
```http
GET /api/bot/:botId
```

**响应**：
```json
{
  "success": true,
  "botId": "1",
  "status": "online",  // connecting | online | offline | close
  "hasQR": false,
  "qr": null  // QR 码 base64（如果有）
}
```

#### 5. 同步群组
```http
POST /api/bot/:botId/sync-groups
```

#### 6. 同步群组用户
```http
POST /api/bot/:botId/sync-group-users
Content-Type: application/json

{
  "groupId": "120363xxxxx@g.us"
}
```

### 辅助接口

```http
GET /                          # 健康检查
GET /sessions                  # 列出所有会话
GET /sessions/:id/qr           # 获取 QR 码（兼容旧版）
GET /sessions/:id/status       # 获取状态（兼容旧版）
```

## 🔄 工作流程

### 方式一：二维码登录
1. **Laravel 后台** → 点击"连接 WhatsApp"
2. **Laravel** → 调用 Node.js API `/api/bot/:id/start`
3. **Node.js** → 生成 WhatsApp QR 码
4. **Node.js** → 将 QR 码发送到 Laravel API
5. **Laravel** → 在页面上显示 QR 码
6. **用户** → 扫描 QR 码
7. **Node.js** → 连接成功，通知 Laravel
8. **Laravel** → 更新机器人状态为"在线"

### 方式二：验证码登录
1. **Laravel 后台** → 选择"验证码登录"，输入手机号
2. **Laravel** → 调用 Node.js API `/api/bot/:id/start-sms`
3. **Node.js** → 请求 WhatsApp 配对码
4. **Node.js** → 返回配对码到 Laravel
5. **Laravel** → 显示配对码
6. **用户** → 在 WhatsApp 中输入配对码
7. **Node.js** → 连接成功，通知 Laravel
8. **Laravel** → 更新机器人状态为"在线"

## 📝 使用说明

### 完整流程：

1. **启动 Node.js 服务器**
   ```bash
   cd whatsapp-bot
   npm start
   ```
   保持此窗口运行！

2. **在 Laravel 后台创建机器人**
   - 访问 `/admin/bots`
   - 点击"创建"，填写机器人信息
   - 保存后记录机器人 ID

3. **连接 WhatsApp**
   - 点击机器人列表中的"连接"按钮
   - 或者进入机器人详情页，点击"连接 WhatsApp"
   - 页面会自动显示 QR 码

4. **扫描 QR 码**
   - 使用手机 WhatsApp 扫描页面上的 QR 码
   - 等待连接成功（页面会自动刷新状态）

5. **查看数据**
   - 机器人连接成功后会自动同步群组数据
   - 在群组管理中查看所有群组
   - 在用户管理中查看用户进群情况

## 🧹 清理过期会话

当出现 **错误 401（会话已过期）** 时，需要清理旧的会话文件：

```bash
# 清理所有会话
node cleanup-sessions.js

# 清理指定会话
node cleanup-sessions.js 1

# 或者通过 API 停止并删除
curl -X POST http://localhost:3000/api/bot/1/stop \
  -H "Content-Type: application/json" \
  -d '{"deleteFiles": true}'
```

清理后在 Laravel 后台重新启动机器人，会生成新的二维码。

## 🔧 故障排查

### ❌ 错误 401：会话已过期

**原因**：WhatsApp 凭据已失效或被其他设备登录

**解决方案**：
```bash
# 1. 清理过期会话
node cleanup-sessions.js 1

# 2. 在 Laravel 后台重新启动机器人
# 3. 重新扫码登录
```

### ❌ 错误 515/428：配对重启失败

**原因**：扫码成功但重连失败

**解决方案**：
- 已自动重试，等待 3 秒
- 如果持续失败，重启 Node.js 服务器
- 检查网络连接

### ❌ 无法启动服务器

**检查项**：
- 3000 端口是否被占用
- Node.js 版本是否 >= 16
- 依赖是否已安装（`npm install`）

### ❌ Laravel 无法连接到 Node.js

**检查项**：
- Node.js 服务器是否运行中
- 端口配置是否正确（默认 3000）
- 防火墙是否阻止连接

### ❌ QR 码不显示

**解决方案**：
1. 检查浏览器控制台错误
2. 刷新 Laravel 页面
3. 重新启动机器人
4. 查看 Node.js 日志

### ❌ 扫码后连接失败

**解决方案**：
1. 确认 WhatsApp 版本最新
2. 检查网络连接是否稳定
3. 清理会话后重试
4. 检查是否被 WhatsApp 限制（频繁登录/登出）

## 🎯 多机器人支持

Node.js 服务器支持同时运行多个机器人：

- 每个机器人有独立的会话存储
- 通过机器人 ID 区分不同实例
- 在 Laravel 后台分别管理每个机器人

## 📞 技术支持

如有问题，请查看日志：
- **Node.js 日志**：查看终端输出
- **Laravel 日志**：`storage/logs/laravel.log`