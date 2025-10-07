module.exports = {
    // Laravel Backend Configuration
    laravel: {
        url: process.env.LARAVEL_URL || 'http://localhost:89',
        apiToken: process.env.LARAVEL_API_TOKEN || 'your_api_token_here'
    },
    
    // WebSocket Configuration
    websocket: {
        url: process.env.WEBSOCKET_URL || 'http://localhost:89',
        namespace: process.env.WEBSOCKET_NAMESPACE || '/bot'
    },
    
    // Bot Configuration
    bot: {
        id: process.env.BOT_ID || 1,
        name: process.env.BOT_NAME || 'Default Bot',
        phone: process.env.BOT_PHONE || '+8613800138000'
    },
    
    // Logging
    logging: {
        level: process.env.LOG_LEVEL || 'info'
    }
};
