<x-filament-panels::page>
    <div class="space-y-6">
        <!-- 机器人信息卡片 -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                    机器人信息
                </div>
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-filament::fieldset>
                    <x-slot name="label">
                        机器人名称
                    </x-slot>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $this->record->name }}
                    </div>
                </x-filament::fieldset>
                
                <x-filament::fieldset>
                    <x-slot name="label">
                        手机号
                    </x-slot>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $this->record->phone_number }}
                    </div>
                </x-filament::fieldset>
                
                <x-filament::fieldset>
                    <x-slot name="label">
                        当前状态
                    </x-slot>
                    <x-filament::badge :color="$this->getStatusColor()" size="lg">
                        {{ $this->record->getStatusLabelAttribute() }}
                    </x-filament::badge>
                </x-filament::fieldset>
            </div>
        </x-filament::section>

        <!-- QR 码显示区域 -->
        @if($this->record->status === 'connecting')
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-qr-code class="w-5 h-5" />
                    WhatsApp 登录
                </div>
            </x-slot>
            
            <div class="text-center space-y-6" wire:poll.2s>
                <!-- QR 码容器 -->
                <div class="flex justify-center">
                    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                        @if(isset($cachedQrCode) && $cachedQrCode)
                            <img src="{{ $cachedQrCode }}" alt="WhatsApp QR Code" class="w-64 h-64 rounded-lg">
                        @else
                            <div class="w-64 h-64 flex items-center justify-center bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <div class="text-center">
                                    <x-filament::loading-indicator class="w-8 h-8 mx-auto mb-3 text-primary-500" />
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
                    
                    <x-filament::fieldset>
                        <x-slot name="label">
                            操作步骤
                        </x-slot>
                        <div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                            <div class="flex items-center gap-3">
                                <x-filament::badge color="primary" size="sm">1</x-filament::badge>
                                <span>打开 WhatsApp 应用</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-filament::badge color="primary" size="sm">2</x-filament::badge>
                                <span>点击右上角菜单 → 链接设备</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-filament::badge color="primary" size="sm">3</x-filament::badge>
                                <span>扫描上方二维码</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-filament::badge color="primary" size="sm">4</x-filament::badge>
                                <span>等待连接成功</span>
                            </div>
                        </div>
                    </x-filament::fieldset>
                    
                    <x-filament::badge color="warning" size="sm" class="mt-4">
                        注意：二维码在 5 分钟内有效
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::section>
        @endif


        <!-- 在线状态 -->
        @if($this->record->status === 'online')
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                    连接状态
                </div>
            </x-slot>
            
            <div class="space-y-6">
                <!-- 连接成功提示 -->
                <x-filament::fieldset>
                    <div class="flex items-center gap-4 p-4 bg-success-50 dark:bg-success-900/20 rounded-lg border border-success-200 dark:border-success-800">
                        <x-heroicon-o-check-circle class="w-8 h-8 text-success-600 flex-shrink-0" />
                        <div>
                            <h4 class="font-semibold text-success-800 dark:text-success-200">WhatsApp 已连接</h4>
                            <p class="text-sm text-success-600 dark:text-success-400">机器人正在正常运行中</p>
                        </div>
                    </div>
                </x-filament::fieldset>
                
                <!-- 统计信息 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-filament::fieldset>
                        <x-slot name="label">
                            最后活跃时间
                        </x-slot>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->record->last_seen ? $this->record->last_seen->format('Y-m-d H:i:s') : '未知' }}
                        </div>
                    </x-filament::fieldset>
                    
                    <x-filament::fieldset>
                        <x-slot name="label">
                            管理的群组
                        </x-slot>
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="info" size="lg">
                                {{ $this->record->groups()->count() }} 个
                            </x-filament::badge>
                        </div>
                    </x-filament::fieldset>
                </div>
            </div>
        </x-filament::section>
        
        <!-- 群组列表 -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-user-group class="w-5 h-5" />
                    WhatsApp 群组列表
                </div>
            </x-slot>
            <x-slot name="description">
                显示机器人当前管理的所有群组，点击"同步群组"按钮刷新列表
            </x-slot>
            
            @if($this->record->groups()->count() > 0)
                <div class="space-y-4">
                    @foreach($this->record->groups as $group)
                    <x-filament::fieldset>
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h4 class="font-semibold text-gray-900 dark:text-white">{{ $group->name }}</h4>
                                    <x-filament::badge color="info" size="sm">
                                        {{ $group->member_count }} 人
                                    </x-filament::badge>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    ID: {{ Str::limit($group->group_id, 40) }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    创建时间: {{ $group->created_at->format('Y-m-d H:i') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($group->hasBatchBinding())
                                    <x-filament::badge color="success" size="sm">
                                        已绑定批次
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="gray" size="sm">
                                        未绑定批次
                                    </x-filament::badge>
                                @endif
                            </div>
                        </div>
                    </x-filament::fieldset>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-user-group class="mx-auto h-8 w-8 text-gray-400 mb-3" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">暂无群组</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        点击上方"同步群组"按钮获取 WhatsApp 群组列表
                    </p>
                </div>
            @endif
        </x-filament::section>
        @endif

        <!-- 离线状态 -->
        @if($this->record->status === 'offline')
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-x-circle class="w-5 h-5 text-danger-500" />
                    连接状态
                </div>
            </x-slot>
            
            <div class="text-center py-8">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-danger-50 dark:bg-danger-900/20 rounded-full mb-4">
                    <x-heroicon-o-x-circle class="w-6 h-6 text-danger-500" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">机器人未连接</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    点击"连接 WhatsApp"开始登录
                </p>
                <x-filament::badge color="warning" size="sm">
                    请确保 Node.js 服务器正在运行
                </x-filament::badge>
            </div>
        </x-filament::section>
        @endif
    </div>

    @script
    <script>
        // 轮询 QR 码
        let pollingInterval = null;
        
        $wire.on('start-qr-polling', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            
            pollingInterval = setInterval(() => {
                $wire.call('checkQrCode');
            }, 2000); // 每2秒检查一次
        });
        
        // 页面离开时清除轮询
        window.addEventListener('beforeunload', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
    </script>
    @endscript
</x-filament-panels::page>
