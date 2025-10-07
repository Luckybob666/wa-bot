# WhatsApp 机器人服务器

## 🎯 架构说明

本项目采用 **Laravel 主控 + Node.js 服务** 的架构：

- **Laravel 后台**：完全控制机器人（创建、启动、停止、查看数据）
- **Node.js 服务**：提供 HTTP API，执行 WhatsApp 连接操作

## 🚀 快速开始

### 1. 安装依赖

```bash
npm install
```

### 2. 启动服务器

```bash
# 方式1：双击启动脚本（Windows）
start.bat

# 方式2：命令行启动
npm start

# 方式3：开发模式（自动重启）
npm run dev
```

### 3. 验证服务器运行

访问 `http://localhost:3000` 应该能看到服务器正在运行。

### 4. 在 Laravel 后台操作

1. 访问 Laravel 后台 `/admin/bots`
2. 创建或选择机器人
3. 点击"连接"按钮
4. 在页面中扫描显示的 QR 码
5. 等待连接成功

## 📡 API 接口

Node.js 服务器提供以下 API：

### 启动机器人
```
POST /api/bot/:botId/start
Body: { "laravelUrl": "http://localhost:89", "apiToken": "" }
```

### 停止机器人
```
POST /api/bot/:botId/stop
```

### 获取 QR 码
```
GET /api/bot/:botId/qr-code
```

### 获取机器人状态
```
GET /api/bot/:botId/status
```

### 获取群组列表
```
GET /api/bot/:botId/groups
```

## 🔄 工作流程

1. **Laravel 后台** → 点击"连接 WhatsApp"
2. **Laravel** → 调用 Node.js API `/api/bot/:id/start`
3. **Node.js** → 生成 WhatsApp QR 码
4. **Node.js** → 将 QR 码发送到 Laravel API
5. **Laravel** → 在页面上显示 QR 码
6. **用户** → 扫描 QR 码
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

## 🔧 故障排查

### 问题1：无法启动服务器
- 检查 3000 端口是否被占用
- 确认 Node.js 已安装
- 查看错误日志

### 问题2：Laravel 无法连接到 Node.js
- 确认 Node.js 服务器正在运行
- 检查端口配置（默认 3000）
- 确认防火墙设置

### 问题3：QR 码不显示
- 检查浏览器控制台错误
- 刷新页面重试
- 重新启动机器人

### 问题4：扫码后无法连接
- 检查 WhatsApp 版本是否最新
- 确认网络连接正常
- 删除 `.wwebjs_auth/bot_*` 文件夹重试

## 🎯 多机器人支持

Node.js 服务器支持同时运行多个机器人：

- 每个机器人有独立的会话存储
- 通过机器人 ID 区分不同实例
- 在 Laravel 后台分别管理每个机器人

## 📞 技术支持

如有问题，请查看日志：
- **Node.js 日志**：查看终端输出
- **Laravel 日志**：`storage/logs/laravel.log`