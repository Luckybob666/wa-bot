<x-filament-panels::page>
    {{ $this->infolist }}

    @script
    <script>
        // 轮询 QR 码和配对码
        let pollingInterval = null;
        let isPolling = false;
        
        $wire.on('start-qr-polling', () => {
            console.log('开始轮询 QR 码和配对码...');
            
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            
            // 立即检查一次
            setTimeout(() => {
                $wire.call('checkQrCode');
            }, 1000);
            
            pollingInterval = setInterval(() => {
                if (!isPolling) {
                    isPolling = true;
                    console.log('轮询检查 QR 码...');
                    $wire.call('checkQrCode').then(() => {
                        isPolling = false;
                    }).catch((error) => {
                        console.error('轮询错误:', error);
                        isPolling = false;
                    });
                } else {
                    console.log('跳过轮询，上次请求还在进行中...');
                }
            }, 3000); // 每3秒检查一次，避免请求冲突
        });
        
        // 停止轮询
        $wire.on('stop-qr-polling', () => {
            console.log('停止轮询...');
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
            isPolling = false;
        });
        
        // 页面离开时清除轮询
        window.addEventListener('beforeunload', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
        
        // 当机器人状态变为 online 时停止轮询
        $wire.on('bot-connected', () => {
            console.log('机器人已连接，停止轮询...');
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
            isPolling = false;
        });
        
        // 处理延迟检查事件
        $wire.on('check-qr-delayed', () => {
            console.log('收到延迟检查 QR 码事件...');
            setTimeout(() => {
                $wire.call('checkQrDelayed');
            }, 3000); // 3秒后检查
        });
    </script>
    @endscript
</x-filament-panels::page>
