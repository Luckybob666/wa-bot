# Laravel 端更新说明 v3.0

## 🎯 主要更新

### 1. 整合页面功能
- ✅ **删除** `ConnectBot.php` 页面
- ✅ **整合** 所有功能到 `ViewBot.php`
- ✅ **简化** 页面结构，一个页面完成所有操作

### 2. 双重登录方式支持

#### **二维码登录**
```php
// 调用原有的 API
$response = Http::post($nodeUrl . '/api/bot/' . $botId . '/start');
```

#### **验证码登录**
```php
// 调用新的 API
$response = Http::post($nodeUrl . '/api/bot/' . $botId . '/start-sms', [
    'phoneNumber' => '60123456789'
]);

$pairingCode = $response->json()['data']['pairingCode'];
```

### 3. 新增界面组件

#### **登录方式选择**
```php
Select::make('loginType')
    ->label('登录方式')
    ->options([
        'qr' => '二维码登录',
        'sms' => '验证码登录'
    ])
    ->reactive()
```

#### **手机号输入**
```php
TextInput::make('phoneNumber')
    ->label('手机号')
    ->placeholder('例如：60123456789')
    ->visible(fn () => $this->loginType === 'sms')
    ->required(fn () => $this->loginType === 'sms')
    ->mask('999999999999999')
```

#### **验证码显示**
- 新增 `pairing-code-display.blade.php` 视图
- 显示配对码和使用说明
- 实时状态更新

## 🔧 功能特性

### 1. 智能界面切换

| 机器人状态 | 显示内容 |
|-----------|----------|
| **offline** | 登录方式选择 + 启动按钮 |
| **connecting (QR)** | 二维码显示区域 |
| **connecting (SMS)** | 验证码显示区域 |
| **online** | 断开连接 + 同步群组按钮 |

### 2. 实时状态监控

```php
public function checkStatus()
{
    // 检查 QR 码（仅二维码登录）
    if ($this->loginType === 'qr') {
        $qrCode = Cache::get("bot_{$this->record->id}_qrcode");
        if (!empty($qrCode)) {
            $this->qrCode = $qrCode;
        }
    }
    
    // 刷新机器人状态
    $this->record->refresh();
    
    // 连接成功后清理状态
    if ($this->record->status === 'online') {
        $this->isPolling = false;
        $this->qrCode = null;
        $this->pairingCode = null;
    }
}
```

### 3. 错误处理优化

```php
try {
    $response = Http::timeout($timeout)->post($nodeUrl . '/api/bot/' . $botId . '/start-sms', [
        'phoneNumber' => $this->phoneNumber
    ]);
    
    if ($response->successful()) {
        $data = $response->json();
        $this->pairingCode = $data['data']['pairingCode'] ?? null;
        
        Notification::make()
            ->title('验证码已生成')
            ->body("配对码：{$this->pairingCode}")
            ->success()
            ->send();
    }
} catch (\Exception $e) {
    Notification::make()
        ->title('连接失败')
        ->body('无法连接到 Node.js 服务器：' . $e->message)
        ->danger()
        ->send();
}
```

## 📱 用户界面

### 1. 登录方式选择

当机器人状态为 `offline` 时显示：

```
┌─────────────────────────────────────┐
│ WhatsApp 登录                       │
├─────────────────────────────────────┤
│ 登录方式: [二维码登录 ▼]            │
│ 手机号: [60123456789     ] (仅SMS)  │
│                                     │
│ [📱 生成二维码] 或 [📞 获取验证码]   │
└─────────────────────────────────────┘
```

### 2. 二维码登录界面

当选择二维码登录时：

```
┌─────────────────────────────────────┐
│ 扫码登录                            │
├─────────────────────────────────────┤
│           [QR码图片]                │
│                                     │
│ 使用手机 WhatsApp 扫描二维码        │
│                                     │
│ 🔄 正在等待连接...                  │
└─────────────────────────────────────┘
```

### 3. 验证码登录界面

当选择验证码登录时：

```
┌─────────────────────────────────────┐
│ 验证码登录                          │
├─────────────────────────────────────┤
│           123-456-789               │
│        手机号：60123456789          │
│                                     │
│ 📱 使用说明：                       │
│ 1. 在手机 WhatsApp 中点击三个点      │
│ 2. 选择"连接设备"                   │
│ 3. 选择"通过配对码连接"             │
│ 4. 输入配对码：123-456-789          │
│                                     │
│ ⏰ 配对码有效期约 2 分钟             │
│ 🔄 正在等待连接...                  │
└─────────────────────────────────────┘
```

## 🚀 使用流程

### 方式一：二维码登录

1. **进入机器人详情页**
2. **选择"二维码登录"**
3. **点击"生成二维码"**
4. **使用手机 WhatsApp 扫描**
5. **等待连接成功**

### 方式二：验证码登录

1. **进入机器人详情页**
2. **选择"验证码登录"**
3. **输入手机号**（如：60123456789）
4. **点击"获取验证码"**
5. **在手机 WhatsApp 中输入配对码**
6. **等待连接成功**

## 🔄 API 兼容性

### 保持向后兼容

- ✅ 原有的二维码登录 API 继续工作
- ✅ 新增验证码登录 API
- ✅ 所有现有功能保持不变

### 新增 API 端点

```bash
# 验证码登录
POST /api/bot/:botId/start-sms
{
  "phoneNumber": "60123456789"
}

# 响应
{
  "success": true,
  "message": "配对码已生成",
  "data": {
    "botId": "1",
    "status": "connecting",
    "pairingCode": "123-456-789"
  }
}
```

## 📋 文件变更

### 新增文件
- `resources/views/filament/resources/bots/pages/pairing-code-display.blade.php`

### 修改文件
- `app/Filament/Resources/Bots/Pages/ViewBot.php` - 完全重构
- `app/Filament/Resources/Bots/BotResource.php` - 移除 ConnectBot 引用

### 删除文件
- `app/Filament/Resources/Bots/Pages/ConnectBot.php` - 功能已整合

## 🎯 测试步骤

### 1. 测试二维码登录

```bash
# 1. 进入机器人详情页
# 2. 选择"二维码登录"
# 3. 点击"生成二维码"
# 4. 观察二维码是否显示
# 5. 用手机扫描测试
```

### 2. 测试验证码登录

```bash
# 1. 进入机器人详情页
# 2. 选择"验证码登录"
# 3. 输入手机号：60123456789
# 4. 点击"获取验证码"
# 5. 观察配对码是否显示
# 6. 在手机 WhatsApp 中输入配对码测试
```

### 3. 测试状态同步

```bash
# 1. 连接成功后
# 2. 点击"同步群组"按钮
# 3. 观察是否显示同步结果
# 4. 点击"断开连接"测试
```

## ✅ 完成

Laravel 端已完全支持双重登录方式，界面更加简洁统一，用户体验显著提升！

所有功能现在都集中在 `ViewBot` 页面中，不再需要单独的连接页面。
