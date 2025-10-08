<x-filament-panels::page>
    {{ $this->infolist }}

    @script
    <script>
        // 轮询 QR 码和配对码
        let pollingInterval = null;
        
        $wire.on('start-qr-polling', () => {
            console.log('开始轮询 QR 码和配对码...');
            
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            
            pollingInterval = setInterval(() => {
                $wire.call('checkQrCode');
            }, 2000); // 每2秒检查一次
        });
        
        // 停止轮询
        $wire.on('stop-qr-polling', () => {
            console.log('停止轮询...');
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        });
        
        // 页面离开时清除轮询
        window.addEventListener('beforeunload', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
        
        // 当机器人状态变为 online 时停止轮询
        $wire.on('bot-connected', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        });
    </script>
    @endscript
</x-filament-panels::page>
