<?php

namespace App\Filament\Resources\Bots\Pages;

use App\Filament\Resources\Bots\BotResource;
use App\Models\Bot;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;

class ConnectBot extends ViewRecord
{
    protected static string $resource = BotResource::class;

    public ?string $qrCode = null;
    public bool $isPolling = false;
    
    protected $listeners = ['poll-qr-code' => 'checkQrCode'];
    
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'cachedQrCode' => Cache::get("bot_{$this->record->id}_qrcode"),
        ]);
    }
    
    // 使用 Filament 默认布局，不自定义视图

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('机器人信息')
                    ->components([
                        Grid::make(3)
                            ->components([
                                Text::make(fn () => '机器人名称：' . $this->record->name),
                                Text::make(fn () => '手机号：' . ($this->record->phone_number ?: '未获取')),
                                Text::make(fn () => match ($this->record->status) {
                                        'online' => '在线',
                                        'connecting' => '连接中',
                                        'offline' => '离线',
                                        'error' => '错误',
                                        default => '未知'
                                    })
                                    ->badge()
                                    ->color(fn (): string => match ($this->record->status) {
                                        'online' => 'success',
                                        'connecting' => 'warning',
                                        'offline' => 'gray',
                                        'error' => 'danger',
                                        default => 'gray'
                                    }),
                            ]),
                    ]),
                
                // QR 码显示区域
                Section::make('WhatsApp 登录')
                    ->visible(fn () => $this->record->status === 'connecting')
                    ->components([
                        View::make('qr_code_display')
                            ->view('filament.resources.bots.pages.qr-code-display')
                            ->viewData([
                                'qrCode' => $this->qrCode,
                                'botId' => $this->record->id,
                            ]),
                    ]),
            ]);
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // 如果机器人正在连接中，开始轮询 QR 码
        if ($this->record->status === 'connecting') {
            $this->startPollingQrCode();
        }
        
        // 立即检查一次 QR 码
        $this->checkQrCode();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('connect_whatsapp')
                ->label('连接 WhatsApp')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->visible(fn () => $this->record->status === 'offline')
                ->action('startBot'),
            
            Actions\Action::make('disconnect_whatsapp')
                ->label('断开连接')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'online')
                ->requiresConfirmation()
                ->action('stopBot'),
            
            Actions\Action::make('sync_groups')
                ->label('同步群组')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn () => $this->record->status === 'online')
                ->action('syncGroups'),
            
            Actions\Action::make('refresh_status')
                ->label('刷新状态')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshStatus'),
            
            Actions\EditAction::make(),
        ];
    }

    public function startBot()
    {
        try {
            // 从配置读取 Node.js 服务器地址
            $nodeUrl = config('app.node_server.url');
            $timeout = config('app.node_server.timeout', 15); // 增加超时到15秒
            
            $response = Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $this->record->id . '/start', [
                'laravelUrl' => url('/'),
                'apiToken' => ''
            ]);
            
            if ($response->successful()) {
                $this->record->update(['status' => 'connecting']);
                $this->record->refresh();
                $this->startPollingQrCode();
                
                Notification::make()
                    ->title('机器人启动中')
                    ->body('正在初始化 WhatsApp 客户端（约需 60-90 秒）...' . PHP_EOL . '请点击"刷新状态"按钮查看进度')
                    ->success()
                    ->duration(10000) // 显示10秒
                    ->send();
            } else {
                Notification::make()
                    ->title('启动失败')
                    ->body('机器人启动失败：' . $response->body())
                    ->danger()
                    ->send();
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // 连接超时是正常的，因为初始化需要时间
            $this->record->update(['status' => 'connecting']);
            $this->record->refresh();
            $this->startPollingQrCode();
            
            Notification::make()
                ->title('机器人正在初始化')
                ->body('首次启动需要 60-90 秒初始化浏览器，请耐心等待...' . PHP_EOL . '点击"刷新状态"查看最新进度')
                ->warning()
                ->duration(15000) // 显示15秒
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('连接失败')
                ->body('无法连接到 Node.js 服务器：' . $e->getMessage() . PHP_EOL . '请确保服务器正在运行 (' . $nodeUrl . ')')
                ->danger()
                ->send();
        }
    }

    public function stopBot()
    {
        try {
            $nodeUrl = config('app.node_server.url');
            $timeout = config('app.node_server.timeout', 10);
            
            $response = Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $this->record->id . '/stop');
            
            $this->record->refresh();
            $this->qrCode = null;
            $this->isPolling = false;
            
            Notification::make()
                ->title('机器人已停止')
                ->body('WhatsApp 连接已断开')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('停止失败')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncGroups()
    {
        try {
            $nodeUrl = config('app.node_server.url');
            $timeout = config('app.node_server.timeout', 10);
            
            $response = Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $this->record->id . '/sync-groups');
            
            if ($response->successful()) {
                $data = $response->json();
                $groupCount = $data['data']['groupCount'] ?? 0;
                
                Notification::make()
                    ->title('群组同步成功')
                    ->body("已同步 {$groupCount} 个群组")
                    ->success()
                    ->send();
                    
                // 刷新页面数据
                $this->dispatch('$refresh');
            } else {
                Notification::make()
                    ->title('同步失败')
                    ->body('无法同步群组：' . $response->body())
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('同步失败')
                ->body('无法连接到 Node.js 服务器')
                ->danger()
                ->send();
        }
    }

    public function refreshStatus()
    {
        $this->record->refresh();
        $this->checkQrCode();
        
        // 强制刷新页面数据
        $this->dispatch('$refresh');
        
        Notification::make()
            ->title('状态已刷新')
            ->body('机器人状态: ' . $this->record->getStatusLabelAttribute() . ($this->qrCode ? ' (QR码已加载)' : ' (无QR码)'))
            ->success()
            ->send();
    }

    public function startPollingQrCode()
    {
        $this->isPolling = true;
        $this->dispatch('start-qr-polling');
    }

    #[On('poll-qr-code')]
    public function checkQrCode()
    {
        try {
            // 直接从缓存获取 QR 码，避免 HTTP 请求超时
            $qrCode = Cache::get("bot_{$this->record->id}_qrcode");
            
            \Log::info("QR 码检查 - 机器人 ID: {$this->record->id}, 缓存状态: " . ($qrCode ? '有数据' : '无数据'));
            
            if (!empty($qrCode)) {
                $this->qrCode = $qrCode;
                \Log::info("QR 码已从缓存加载到前端，机器人 ID: {$this->record->id}, QR 码长度: " . strlen($this->qrCode));
            } else {
                \Log::info("缓存中暂无 QR 码，机器人 ID: {$this->record->id}");
            }
        } catch (\Exception $e) {
            \Log::error("获取 QR 码失败: " . $e->getMessage());
        }
        
        // 刷新机器人状态
        $this->record->refresh();
        
        // 如果状态变为 online，停止轮询
        if ($this->record->status === 'online') {
            $this->isPolling = false;
            $this->qrCode = null;
            
            Notification::make()
                ->title('连接成功')
                ->body('WhatsApp 已成功连接！')
                ->success()
                ->send();
        }
        
        // 如果正在连接中但没有 QR 码，开始轮询
        if ($this->record->status === 'connecting' && !$this->qrCode && !$this->isPolling) {
            $this->startPollingQrCode();
        }
        
        // 强制刷新页面数据
        $this->dispatch('$refresh');
    }

    public function getStatusColor(): string
    {
        return match ($this->record->status) {
            'online' => 'success',
            'connecting' => 'warning',
            'offline' => 'gray',
            'error' => 'danger',
            default => 'gray'
        };
    }
}