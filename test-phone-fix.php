<?php
/**
 * 测试手机号修复功能
 * 用法: php test-phone-fix.php
 */

require_once 'vendor/autoload.php';

// 模拟 WhatsappUser 类
class TestWhatsappUser {
    public $phone_number;
    
    public function __construct($phone_number) {
        $this->phone_number = $phone_number;
    }
    
    public function getFormattedPhoneNumberAttribute() {
        if (!$this->phone_number) return '';
        
        // 移除所有非数字字符
        $digits = preg_replace('/\D/', '', $this->phone_number);
        
        // 检查长度是否合理
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return $this->phone_number; // 返回原始值
        }
        
        // 格式化显示
        if (strlen($digits) > 10) {
            // 国际号码格式：+60 12-345 6789
            $countryCode = substr($digits, 0, -10);
            $localNumber = substr($digits, -10);
            return "+{$countryCode} " . substr($localNumber, 0, 2) . "-" . 
                   substr($localNumber, 2, 3) . " " . substr($localNumber, 5);
        } else {
            // 本地号码格式：012-345 6789
            return substr($digits, 0, 3) . "-" . substr($digits, 3, 3) . " " . 
                   substr($digits, 6);
        }
    }
    
    public function hasAbnormalPhoneNumber() {
        if (!$this->phone_number) return false;
        
        $digits = preg_replace('/\D/', '', $this->phone_number);
        return strlen($digits) > 15 || !preg_match('/^\d+$/', $digits);
    }
}

// 测试数据
$testCases = [
    '148932587991082',      // 你看到的异常号码
    '12636313882861',       // 你看到的异常号码
    '60123456789',          // 正常马来西亚号码
    '60123456789:16@s.whatsapp.net', // 包含 JID 格式
    '601234567890',         // 正常国际号码
    '0123456789',           // 正常本地号码
    'abc123def',            // 包含字母
    '123',                  // 太短
    '12345678901234567890', // 太长
];

echo "🧪 手机号修复功能测试\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($testCases as $phone) {
    $user = new TestWhatsappUser($phone);
    
    echo "原始手机号: {$phone}\n";
    echo "格式化后:   {$user->formatted_phone_number}\n";
    echo "是否异常:   " . ($user->hasAbnormalPhoneNumber() ? '是 ⚠️' : '否 ✅') . "\n";
    echo str_repeat("-", 40) . "\n";
}

echo "\n🎯 修复建议:\n";
echo "1. 重启 Node.js 服务器以应用新的手机号提取逻辑\n";
echo "2. 清理现有的异常数据: php artisan whatsapp:fix-phone-numbers --dry-run\n";
echo "3. 重新同步群组用户数据\n";
echo "4. 检查 Laravel 后台显示是否正常\n";
