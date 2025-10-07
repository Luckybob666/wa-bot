<div class="space-y-4">
    <div class="text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
            <x-heroicon-o-device-phone-mobile class="w-8 h-8 text-blue-600" />
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">验证码登录</h3>
        <p class="text-sm text-gray-600 mb-4">
            请在 WhatsApp 中输入以下配对码
        </p>
    </div>

    <div class="bg-gray-50 rounded-lg p-6 text-center">
        <div class="text-2xl font-mono font-bold text-gray-900 mb-2 tracking-wider">
            {{ $pairingCode }}
        </div>
        <div class="text-sm text-gray-600">
            手机号：{{ $phoneNumber }}
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
            <div class="text-sm text-blue-800">
                <p class="font-medium mb-1">使用说明：</p>
                <ol class="list-decimal list-inside space-y-1 text-blue-700">
                    <li>在手机 WhatsApp 中，点击右上角三个点</li>
                    <li>选择"连接设备"或"链接设备"</li>
                    <li>选择"通过配对码连接"</li>
                    <li>输入上面的配对码：<strong>{{ $pairingCode }}</strong></li>
                    <li>等待连接成功</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="text-center">
        <div class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
            <x-heroicon-o-clock class="w-4 h-4 mr-2" />
            配对码有效期约 2 分钟
        </div>
    </div>

    @if($isPolling ?? false)
        <div class="text-center">
            <div class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-green-600 mr-2"></div>
                正在等待连接...
            </div>
        </div>
    @endif
</div>
