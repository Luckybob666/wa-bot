module.exports = {
  apps: [{
    name: 'whatsapp-bot',
    script: 'server.js',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '1G',
    env: {
      NODE_ENV: 'production',
      LARAVEL_URL: 'http://localhost:89',
      PORT: 3000,
      LOG_LEVEL: 'info'
    },
    env_production: {
      NODE_ENV: 'production',
      LARAVEL_URL: 'https://your-domain.com',
      PORT: 3000,
      LOG_LEVEL: 'info'
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_file: './logs/combined.log',
    time: true,
    // 重启策略
    min_uptime: '10s',
    max_restarts: 10,
    // 健康检查
    health_check_grace_period: 3000,
    // 集群模式（如果需要）
    // instances: 'max',
    // exec_mode: 'cluster'
  }]
};
