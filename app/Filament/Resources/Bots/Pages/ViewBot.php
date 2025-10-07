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
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Select;
use Filament\Forms\Components\Actions\Action as FormAction;

class ViewBot extends ViewRecord
{
    protected static string $resource = BotResource::class;

    public ?string $qrCode = null;
    public bool $isPolling = false;
    public string $loginType = 'qr'; // 'qr' 或 'sms'
    public string $phoneNumber = '';
    public ?string $pairingCode = null;
    public bool $showSmsForm = false;
    
    protected $listeners = ['poll-qr-code' => 'checkQrCode'];
    
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'cachedQrCode' => Cache::get("bot_{$this->record->id}_qrcode"),
        ]);
    }

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
                
                // 登录方式选择
                Section::make('WhatsApp 登录')
                    ->visible(fn () => $this->record->status === 'offline')
                    ->components([
                        Grid::make(2)
                            ->components([
                                Select::make('loginType')
                                    ->label('登录方式')
                                    ->options([
                                        'qr' => '二维码登录',
                                        'sms' => '验证码登录'
                                    ])
                                    ->default('qr')
                                    ->reactive()
                                    ->afterStateUpdated(fn () => $this->showSmsForm = $this->loginType === 'sms'),
                                
                                TextInput::make('phoneNumber')
                                    ->label('手机号')
                                    ->placeholder('例如：60123456789')
                                    ->visible(fn () => $this->loginType === 'sms')
                                    ->required(fn () => $this->loginType === 'sms')
                                    ->mask('999999999999999')
                                    ->helperText('请输入完整的手机号（包含国家代码，如：60123456789）'),
                            ]),
                        
                        Actions\Action::make('start_connection')
                            ->label(fn () => $this->loginType === 'qr' ? '生成二维码' : '获取验证码')
                            ->icon(fn () => $this->loginType === 'qr' ? 'heroicon-o-qr-code' : 'heroicon-o-device-phone-mobile')
                            ->color('success')
                            ->action('startBot')
                            ->disabled(fn () => $this->loginType === 'sms' && empty($this->phoneNumber)),
                    ]),
                
                // QR 码显示区域
                Section::make('扫码登录')
                    ->visible(fn () => $this->record->status === 'connecting' && $this->loginType === 'qr')
                    ->components([
                        View::make('qr_code_display')
                            ->view('filament.resources.bots.pages.qr-code-display')
                            ->viewData([
                                'qrCode' => $this->qrCode,
                                'botId' => $this->record->id,
                            ]),
                    ]),
                
                // 验证码显示区域
                Section::make('验证码登录')
                    ->visible(fn () => $this->record->status === 'connecting' && $this->loginType === 'sms' && $this->pairingCode)
                    ->components([
                        View::make('pairing_code_display')
                            ->view('filament.resources.bots.pages.pairing-code-display')
                            ->viewData([
                                'pairingCode' => $this->pairingCode,
                                'phoneNumber' => $this->phoneNumber,
                                'botId' => $this->record->id,
                            ]),
                    ]),
            ]);
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // 如果机器人正在连接中，开始轮询
        if ($this->record->status === 'connecting') {
            $this->startPolling();
        }
        
        // 立即检查一次状态
        $this->checkStatus();
    }

    protected function getHeaderActions(): array
    {
        return [
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
            $nodeUrl = config('app.node_server.url');
            $timeout = config('app.node_server.timeout', 15);
            
            if ($this->loginType === 'qr') {
                // 二维码登录
                $response = Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $this->record->id . '/start', [
                    'laravelUrl' => url('/'),
                    'apiToken' => ''
                ]);
                
                if ($response->successful()) {
                    $this->record->update(['status' => 'connecting']);
                    $this->record->refresh();
                    $this->startPolling();
                    
                    Notification::make()
                        ->title('机器人启动中')
                        ->body('正在生成二维码，请稍候...')
                        ->success()
                        ->duration(5000)
                        ->send();
                } else {
                    Notification::make()
                        ->title('启动失败')
                        ->body('机器人启动失败：' . $response->body())
                        ->danger()
                        ->send();
                }
            } else {
                // 验证码登录
                if (empty($this->phoneNumber)) {
                    Notification::make()
                        ->title('请输入手机号')
                        ->body('验证码登录需要输入手机号')
                        ->warning()
                        ->send();
                    return;
                }
                
                $response = Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $this->record->id . '/start-sms', [
                    'phoneNumber' => $this->phoneNumber
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $this->pairingCode = $data['data']['pairingCode'] ?? null;
                    
                    $this->record->update(['status' => 'connecting']);
                    $this->record->refresh();
                    $this->startPolling();
                    
                    Notification::make()
                        ->title('验证码已生成')
                        ->body("配对码：{$this->pairingCode}" . PHP_EOL . '请在 WhatsApp 中输入此配对码')
                        ->success()
                        ->duration(15000)
                        ->send();
                } else {
                    Notification::make()
                        ->title('获取验证码失败')
                        ->body('无法获取配对码：' . $response->body())
                        ->danger()
                        ->send();
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // 连接超时处理
            $this->record->update(['status' => 'connecting']);
            $this->record->refresh();
            $this->startPolling();
            
            Notification::make()
                ->title('机器人正在初始化')
                ->body('首次启动需要 60-90 秒初始化，请耐心等待...')
                ->warning()
                ->duration(15000)
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('连接失败')
                ->body('无法连接到 Node.js 服务器：' . $e->getMessage())
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
            $this->pairingCode = null;
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
                $syncedCount = $data['data']['syncedCount'] ?? 0;
                $totalGroups = $data['data']['totalGroups'] ?? 0;
                
                Notification::make()
                    ->title('群组同步成功')
                    ->body("已同步 {$syncedCount}/{$totalGroups} 个群组")
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
        $this->checkStatus();
        
        // 强制刷新页面数据
        $this->dispatch('$refresh');
        
        Notification::make()
            ->title('状态已刷新')
            ->body('机器人状态: ' . $this->record->getStatusLabelAttribute())
            ->success()
            ->send();
    }

    public function startPolling()
    {
        $this->isPolling = true;
        $this->dispatch('start-qr-polling');
    }

    #[On('poll-qr-code')]
    public function checkQrCode()
    {
        $this->checkStatus();
    }

    public function checkStatus()
    {
        try {
            // 检查 QR 码（仅二维码登录）
            if ($this->loginType === 'qr') {
                $qrCode = Cache::get("bot_{$this->record->id}_qrcode");
                
                if (!empty($qrCode)) {
                    $this->qrCode = $qrCode;
                }
            }
            
            // 刷新机器人状态
            $this->record->refresh();
            
            // 如果状态变为 online，停止轮询
            if ($this->record->status === 'online') {
                $this->isPolling = false;
                $this->qrCode = null;
                $this->pairingCode = null;
                
                Notification::make()
                    ->title('连接成功')
                    ->body('WhatsApp 已成功连接！')
                    ->success()
                    ->send();
            }
            
            // 如果正在连接中但没有状态更新，继续轮询
            if ($this->record->status === 'connecting' && !$this->isPolling) {
                $this->startPolling();
            }
            
            // 强制刷新页面数据
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            \Log::error("检查状态失败: " . $e->getMessage());
        }
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
