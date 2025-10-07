# 宝塔面板部署指南

## 📋 服务器要求

### 系统要求
- **操作系统**：Ubuntu 20.04+ / CentOS 7+ / Debian 10+
- **内存**：至少 2GB RAM（推荐 4GB+）
- **存储**：至少 20GB 可用空间
- **网络**：稳定的网络连接

### 软件要求
- **宝塔面板**：7.0+
- **PHP**：8.2+
- **MySQL**：8.0+
- **Nginx**：1.20+
- **Node.js**：18+

## 🛠️ 宝塔面板环境配置

### 1. 安装宝塔面板
```bash
# CentOS
yum install -y wget && wget -O install.sh http://download.bt.cn/install/install_6.0.sh && sh install.sh ed8484bec

# Ubuntu/Debian
wget -O install.sh http://download.bt.cn/install/install-ubuntu_6.0.sh && sudo bash install.sh ed8484bec
```

### 2. 安装必要软件

#### 在宝塔面板中安装：
- **Nginx** 1.20+
- **MySQL** 8.0+
- **PHP** 8.2（安装扩展：fileinfo、opcache、redis、zip）
- **Node.js** 18+
- **PM2**（Node.js 进程管理）

#### 安装 PHP 扩展：
```bash
# 进入宝塔面板 -> 软件商店 -> PHP 8.2 -> 设置 -> 安装扩展
fileinfo
opcache
redis
zip
curl
gd
mbstring
pdo_mysql
```

## 🚀 项目部署步骤

### 1. 创建网站
1. 进入宝塔面板 -> 网站 -> 添加站点
2. 域名：`your-domain.com`
3. 根目录：`/www/wwwroot/whatsapp-bot`
4. PHP版本：8.2

### 2. 上传项目文件
```bash
# 方法1：通过宝塔面板文件管理器上传
# 方法2：通过 Git 克隆
cd /www/wwwroot/whatsapp-bot
git clone https://github.com/your-username/whatsapp-group-bot.git .
```

### 3. 安装后端依赖
```bash
cd /www/wwwroot/whatsapp-bot
composer install --no-dev --optimize-autoloader
```

### 4. 配置环境变量
```bash
cp .env.example .env
nano .env
```

#### 生产环境 .env 配置：
```env
APP_NAME="WhatsApp Bot"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=whatsapp_bot
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

NODE_SERVER_URL=http://127.0.0.1:3000
NODE_SERVER_TIMEOUT=30

APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=zh_CN
```

### 5. 生成应用密钥
```bash
php artisan key:generate
```

### 6. 数据库配置
```bash
# 创建数据库
# 在宝塔面板 -> 数据库 -> 添加数据库
# 数据库名：whatsapp_bot
# 用户名：whatsapp_user
# 密码：your_password

# 运行迁移
php artisan migrate

# 填充初始数据
php artisan db:seed
```

### 7. 设置文件权限
```bash
chown -R www:www /www/wwwroot/whatsapp-bot
chmod -R 755 /www/wwwroot/whatsapp-bot
chmod -R 775 /www/wwwroot/whatsapp-bot/storage
chmod -R 775 /www/wwwroot/whatsapp-bot/bootstrap/cache
```

## 🤖 部署 Node.js 机器人服务

### 1. 安装机器人服务依赖
```bash
cd /www/wwwroot/whatsapp-bot/whatsapp-bot
npm install --production
```

### 2. 配置机器人服务环境
```bash
cp env.example.txt .env
nano .env
```

#### 机器人服务 .env 配置：
```env
LARAVEL_URL=https://your-domain.com
PORT=3000
LOG_LEVEL=info
```

### 3. 使用 PM2 管理 Node.js 服务

#### 创建 PM2 配置文件：
```bash
cd /www/wwwroot/whatsapp-bot/whatsapp-bot
nano ecosystem.config.js
```

#### ecosystem.config.js 内容：
```javascript
module.exports = {
  apps: [{
    name: 'whatsapp-bot',
    script: 'server.js',
    cwd: '/www/wwwroot/whatsapp-bot/whatsapp-bot',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '1G',
    env: {
      NODE_ENV: 'production',
      LARAVEL_URL: 'https://your-domain.com',
      PORT: 3000,
      LOG_LEVEL: 'info'
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_file: './logs/combined.log',
    time: true
  }]
};
```

#### 启动服务：
```bash
pm2 start ecosystem.config.js
pm2 save
pm2 startup
```

## 🌐 Nginx 配置

### 1. 网站 Nginx 配置
在宝塔面板 -> 网站 -> 设置 -> 配置文件，添加以下配置：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /www/wwwroot/whatsapp-bot/public;
    index index.php index.html;

    # 强制 HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /www/wwwroot/whatsapp-bot/public;
    index index.php index.html;

    # SSL 配置（宝塔面板会自动配置）
    ssl_certificate /www/server/panel/vhost/cert/your-domain.com/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/your-domain.com/privkey.pem;

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip 压缩
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # 处理 Laravel 路由
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # 禁止访问敏感文件
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ /(storage|bootstrap/cache) {
        deny all;
    }

    # 机器人服务代理（如果需要通过域名访问）
    location /api/bot/ {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 2. 防火墙配置
在宝塔面板 -> 安全 -> 防火墙，开放端口：
- **80** (HTTP)
- **443** (HTTPS)
- **3000** (Node.js 服务，可选)

## 🔧 系统优化

### 1. PHP 优化
在宝塔面板 -> PHP 8.2 -> 性能调整：
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### 2. MySQL 优化
在宝塔面板 -> 数据库 -> 性能调整：
```ini
innodb_buffer_pool_size = 256M
max_connections = 100
query_cache_size = 32M
```

### 3. Redis 配置
在宝塔面板 -> Redis -> 配置：
```ini
maxmemory 128mb
maxmemory-policy allkeys-lru
```

## 📊 监控和维护

### 1. 日志监控
```bash
# Laravel 日志
tail -f /www/wwwroot/whatsapp-bot/storage/logs/laravel.log

# Node.js 日志
pm2 logs whatsapp-bot

# Nginx 日志
tail -f /www/wwwlogs/your-domain.com.log
```

### 2. 定期备份
在宝塔面板 -> 计划任务：
- **数据库备份**：每日备份
- **网站备份**：每周备份

### 3. 性能监控
- 使用宝塔面板监控功能
- 设置资源使用告警
- 定期检查磁盘空间

## 🚨 故障排除

### 常见问题

#### 1. 502 Bad Gateway
```bash
# 检查 PHP-FPM 状态
systemctl status php-fpm-82

# 重启 PHP-FPM
systemctl restart php-fpm-82
```

#### 2. Node.js 服务无法启动
```bash
# 检查 PM2 状态
pm2 status

# 重启服务
pm2 restart whatsapp-bot

# 查看错误日志
pm2 logs whatsapp-bot --err
```

#### 3. 数据库连接失败
- 检查数据库服务状态
- 验证数据库凭据
- 检查防火墙设置

#### 4. 权限问题
```bash
chown -R www:www /www/wwwroot/whatsapp-bot
chmod -R 755 /www/wwwroot/whatsapp-bot
chmod -R 775 /www/wwwroot/whatsapp-bot/storage
chmod -R 775 /www/wwwroot/whatsapp-bot/bootstrap/cache
```

## 🔐 安全建议

1. **定期更新**：保持系统和软件最新
2. **强密码**：使用复杂的数据库和面板密码
3. **SSL 证书**：启用 HTTPS
4. **防火墙**：只开放必要端口
5. **备份策略**：定期备份数据和文件
6. **监控日志**：定期检查访问和错误日志

---

**部署完成后，访问 `https://your-domain.com` 即可使用系统！**
