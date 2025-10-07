#!/bin/bash

# WhatsApp Bot 宝塔部署脚本
# 使用方法: bash deploy.sh

set -e

echo "🚀 开始部署 WhatsApp Bot..."

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 配置变量
PROJECT_DIR="/www/wwwroot/whatsapp-bot"
DOMAIN="your-domain.com"
DB_NAME="whatsapp_bot"
DB_USER="whatsapp_user"
DB_PASS=""

# 获取用户输入
echo -e "${YELLOW}请输入数据库密码:${NC}"
read -s DB_PASS

echo -e "${YELLOW}请输入域名 (当前: $DOMAIN):${NC}"
read -r input_domain
if [ ! -z "$input_domain" ]; then
    DOMAIN=$input_domain
fi

echo -e "${YELLOW}请输入项目目录 (当前: $PROJECT_DIR):${NC}"
read -r input_dir
if [ ! -z "$input_dir" ]; then
    PROJECT_DIR=$input_dir
fi

echo -e "${GREEN}配置信息:${NC}"
echo "项目目录: $PROJECT_DIR"
echo "域名: $DOMAIN"
echo "数据库: $DB_NAME"
echo "数据库用户: $DB_USER"

# 创建项目目录
echo -e "${YELLOW}创建项目目录...${NC}"
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# 检查是否已存在项目
if [ -d ".git" ]; then
    echo -e "${YELLOW}项目已存在，更新代码...${NC}"
    git pull origin main
else
    echo -e "${YELLOW}克隆项目...${NC}"
    echo "请手动克隆项目到 $PROJECT_DIR"
    echo "git clone https://github.com/your-username/whatsapp-group-bot.git ."
    exit 1
fi

# 安装 PHP 依赖
echo -e "${YELLOW}安装 PHP 依赖...${NC}"
composer install --no-dev --optimize-autoloader

# 配置环境变量
echo -e "${YELLOW}配置环境变量...${NC}"
if [ ! -f ".env" ]; then
    cp .env.example .env
fi

# 更新 .env 文件
sed -i "s/APP_URL=.*/APP_URL=https:\/\/$DOMAIN/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env

# 生成应用密钥
echo -e "${YELLOW}生成应用密钥...${NC}"
php artisan key:generate

# 运行数据库迁移
echo -e "${YELLOW}运行数据库迁移...${NC}"
php artisan migrate --force

# 填充初始数据
echo -e "${YELLOW}填充初始数据...${NC}"
php artisan db:seed --force

# 设置文件权限
echo -e "${YELLOW}设置文件权限...${NC}"
chown -R www:www $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache

# 安装 Node.js 依赖
echo -e "${YELLOW}安装 Node.js 依赖...${NC}"
cd whatsapp-bot
npm install --production

# 配置机器人服务环境
if [ ! -f ".env" ]; then
    cp env.example.txt .env
fi

sed -i "s/LARAVEL_URL=.*/LARAVEL_URL=https:\/\/$DOMAIN/" .env

# 创建日志目录
mkdir -p logs

# 使用 PM2 启动 Node.js 服务
echo -e "${YELLOW}启动 Node.js 服务...${NC}"
pm2 stop whatsapp-bot 2>/dev/null || true
pm2 start ecosystem.config.js --env production
pm2 save

# 创建 Nginx 配置文件
echo -e "${YELLOW}创建 Nginx 配置...${NC}"
cat > /tmp/whatsapp-bot-nginx.conf << EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $PROJECT_DIR/public;
    index index.php index.html;

    # 强制 HTTPS
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name $DOMAIN;
    root $PROJECT_DIR/public;
    index index.php index.html;

    # SSL 配置（需要宝塔面板配置 SSL 证书）
    ssl_certificate /www/server/panel/vhost/cert/$DOMAIN/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/$DOMAIN/privkey.pem;

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Gzip 压缩
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # 处理 Laravel 路由
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP 处理
    location ~ \.php\$ {
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)\$ {
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
}
EOF

echo -e "${GREEN}部署完成！${NC}"
echo ""
echo -e "${YELLOW}下一步操作:${NC}"
echo "1. 在宝塔面板中创建网站: $DOMAIN"
echo "2. 将 Nginx 配置复制到网站配置中"
echo "3. 在宝塔面板中配置 SSL 证书"
echo "4. 创建数据库: $DB_NAME"
echo "5. 访问 https://$DOMAIN 测试部署"
echo ""
echo -e "${GREEN}Nginx 配置文件已保存到: /tmp/whatsapp-bot-nginx.conf${NC}"
echo -e "${GREEN}Node.js 服务已启动，使用 'pm2 status' 查看状态${NC}"
