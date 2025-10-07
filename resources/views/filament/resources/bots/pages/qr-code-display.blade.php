<div class="space-y-6">
    <!-- QR 码显示区域 -->
    <div class="text-center">
        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 inline-block">
            @if($qrCode)
                <img src="{{ $qrCode }}" alt="WhatsApp QR Code" class="w-64 h-64 rounded-lg">
            @else
                <div class="w-64 h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500 mx-auto mb-3"></div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">正在生成二维码...</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
    
    <!-- 操作说明 -->
    <div class="max-w-md mx-auto">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            请使用 WhatsApp 扫描此二维码
        </h3>
        
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 dark:text-white mb-3">操作步骤</h4>
            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-primary-500 text-white text-xs font-medium rounded-full">1</span>
                    <span>打开 WhatsApp 应用</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-primary-500 text-white text-xs font-medium rounded-full">2</span>
                    <span>点击右上角菜单 → 链接设备</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-primary-500 text-white text-xs font-medium rounded-full">3</span>
                    <span>扫描上方二维码</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-primary-500 text-white text-xs font-medium rounded-full">4</span>
                    <span>等待连接成功</span>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                注意：二维码在 5 分钟内有效
            </span>
        </div>
    </div>
</div>

@script
<script>
    // 自动刷新 QR 码
    let pollInterval;
    
    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        
        pollInterval = setInterval(() => {
            console.log('轮询 QR 码...');
            $wire.call('checkQrCode');
        }, 2000);
    }
    
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }
    
    // 页面加载时开始轮询
    document.addEventListener('DOMContentLoaded', () => {
        startPolling();
    });
    
    // 页面卸载时停止轮询
    document.addEventListener('beforeunload', () => {
        stopPolling();
    });
</script>
@endscript
