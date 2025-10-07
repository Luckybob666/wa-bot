@echo off
chcp 65001 >nul
echo ========================================
echo 🤖 WhatsApp 机器人服务器启动程序
echo ========================================
echo.
echo 正在启动 HTTP API 服务器...
echo 端口: 3000
echo.
echo 说明：
echo - 此服务器提供 API 接口供 Laravel 调用
echo - 请保持此窗口运行
echo - Laravel 后台会通过 API 控制机器人
echo.
echo ========================================
echo.
node server.js
pause
