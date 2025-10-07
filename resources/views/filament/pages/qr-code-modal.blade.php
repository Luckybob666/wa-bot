<div class="space-y-6">
    <!-- QR 码显示 -->
    <div class="flex justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <img src="{{ $qrCode }}" alt="WhatsApp QR Code" class="w-64 h-64">
        </div>
    </div>
    
    <!-- 说明文字 -->
    <div class="space-y-3">
        <p class="text-lg font-semibold text-gray-800 text-center">
            请使用 WhatsApp 扫描此二维码
        </p>
        
        <div class="bg-gray-50 p-4 rounded-lg">
            <ol class="space-y-2 text-sm text-gray-700">
                @foreach($instructions as $instruction)
                    <li class="flex items-start">
                        <span class="text-primary-600 font-semibold mr-2">•</span>
                        <span>{{ $instruction }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
        
        <div class="text-center text-xs text-gray-500 mt-4">
            <p>注意：二维码仅在几分钟内有效，请尽快扫描</p>
        </div>
    </div>
</div>
