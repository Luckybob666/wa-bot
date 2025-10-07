# WhatsApp 群用户统计机器人

一个基于 Laravel + Filament + Node.js (Baileys) 的 WhatsApp 群组管理和用户统计系统。

## ✨ 功能特性

- 🤖 **多机器人管理**：支持多个 WhatsApp 账号同时管理
- 📱 **后端扫码登录**：通过 Web 界面扫码登录 WhatsApp
- 👥 **群组管理**：自动同步群组信息，管理群成员
- 📊 **用户统计**：统计群组用户信息，支持批量比对
- 📋 **手机号批次管理**：批量导入手机号，比对群组成员
- 🔄 **实时同步**：实时同步群组和用户数据
- 📈 **数据可视化**：通过 Filament 管理面板查看数据

## 🛠️ 技术栈

### 后端
- **Laravel 11** - PHP 框架
- **Filament 4** - 管理面板
- **MySQL** - 数据库

### 机器人服务
- **Node.js** - 运行时
- **@whiskeysockets/baileys** - WhatsApp 客户端
- **Express** - Web 服务器

## 📋 系统要求

- PHP >= 8.2
- Node.js >= 18
- MySQL >= 8.0
- Composer
- NPM/Yarn

## 🚀 快速开始

### 1. 克隆项目
```bash
git clone <repository-url>
cd whatsapp-group-bot
```

### 2. 安装后端依赖
```bash
composer install
```

### 3. 配置环境变量
```bash
cp .env.example .env
# 编辑 .env 文件，配置数据库等信息
```

### 4. 数据库设置
```bash
php artisan migrate
php artisan db:seed
```

### 5. 安装机器人服务依赖
```bash
cd whatsapp-bot
npm install
```

### 6. 启动服务

#### 后端服务
```bash
php artisan serve
```

#### 机器人服务
```bash
cd whatsapp-bot
node server.js
```

## 🚀 宝塔面板部署

### 快速部署
```bash
# 克隆项目
git clone https://github.com/your-username/whatsapp-group-bot.git
cd whatsapp-group-bot

# 运行部署脚本
bash deploy.sh
```

### 手动部署
详细部署步骤请参考 [DEPLOYMENT.md](DEPLOYMENT.md)

## 🔧 配置说明

### 环境变量

#### Laravel (.env)
```env
APP_NAME="WhatsApp Bot"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=whatsapp_bot
DB_USERNAME=your_username
DB_PASSWORD=your_password

NODE_SERVER_URL=http://localhost:3000
NODE_SERVER_TIMEOUT=30
```

#### Node.js (whatsapp-bot/.env)
```env
LARAVEL_URL=http://localhost:89
PORT=3000
LOG_LEVEL=info
```

## 📱 使用说明

1. **创建机器人**：在管理面板创建新的机器人实例
2. **扫码登录**：点击连接按钮，扫描二维码登录 WhatsApp
3. **同步群组**：登录成功后同步 WhatsApp 群组
4. **导入手机号**：创建手机号批次，导入需要比对的号码
5. **绑定群组**：将手机号批次绑定到群组
6. **查看结果**：查看比对结果和统计数据

## 🔒 安全注意事项

- 确保生产环境使用 HTTPS
- 定期备份数据库
- 设置强密码和访问控制
- 监控服务器资源使用情况

## 📝 开发说明

### 项目结构
```
├── app/                    # Laravel 应用代码
│   ├── Filament/          # Filament 资源
│   ├── Models/            # Eloquent 模型
│   └── ...
├── whatsapp-bot/          # Node.js 机器人服务
│   ├── server.js          # 主服务器文件
│   ├── package.json       # 依赖配置
│   └── sessions/          # WhatsApp 会话存储
├── database/              # 数据库迁移和种子
├── routes/                # 路由定义
└── resources/             # 视图和资源文件
```

### API 接口

#### 机器人管理
- `POST /api/bot/{id}/start` - 启动机器人
- `POST /api/bot/{id}/stop` - 停止机器人
- `GET /api/bot/{id}/qr-code` - 获取二维码

#### 数据同步
- `POST /api/bot/{id}/sync-group` - 同步群组
- `POST /api/bot/{id}/sync-group-user` - 同步群用户

## 🤝 贡献指南

1. Fork 项目
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 打开 Pull Request

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🆘 支持

如有问题或建议，请：

1. 查看 [Issues](https://github.com/your-username/whatsapp-group-bot/issues)
2. 创建新的 Issue
3. 联系维护者

## 📊 项目状态

![Build Status](https://img.shields.io/badge/build-passing-brightgreen)
![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)

---

**注意**：本项目仅供学习和研究使用，请遵守相关法律法规和 WhatsApp 服务条款。