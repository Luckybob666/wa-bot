<?php

namespace App\Console\Commands;

use App\Models\WhatsappUser;
use Illuminate\Console\Command;

class FixPhoneNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:fix-phone-numbers {--dry-run : 预览将要修复的数据，不实际修改}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复异常的 WhatsApp 用户手机号格式';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('开始检查异常手机号...');
        
        // 查找所有用户
        $users = WhatsappUser::all();
        $abnormalUsers = [];
        
        foreach ($users as $user) {
            if ($user->hasAbnormalPhoneNumber()) {
                $abnormalUsers[] = $user;
            }
        }
        
        if (empty($abnormalUsers)) {
            $this->info('✅ 没有发现异常手机号');
            return 0;
        }
        
        $this->warn("⚠️  发现 " . count($abnormalUsers) . " 个异常手机号:");
        
        $headers = ['ID', '原始手机号', '长度', '格式化后', '状态'];
        $rows = [];
        
        foreach ($abnormalUsers as $user) {
            $rows[] = [
                $user->id,
                $user->phone_number,
                strlen($user->phone_number),
                $user->formatted_phone_number,
                $user->hasAbnormalPhoneNumber() ? '异常' : '正常'
            ];
        }
        
        $this->table($headers, $rows);
        
        if ($isDryRun) {
            $this->info('🔍 这是预览模式，没有修改任何数据');
            $this->info('要实际修复，请运行: php artisan whatsapp:fix-phone-numbers');
            return 0;
        }
        
        if (!$this->confirm('确定要修复这些异常手机号吗？')) {
            $this->info('操作已取消');
            return 0;
        }
        
        $fixedCount = 0;
        $errorCount = 0;
        
        foreach ($abnormalUsers as $user) {
            try {
                // 尝试清理手机号
                $originalPhone = $user->phone_number;
                $cleanPhone = $this->cleanPhoneNumber($originalPhone);
                
                if ($cleanPhone && $cleanPhone !== $originalPhone) {
                    $user->update(['phone_number' => $cleanPhone]);
                    $this->line("✅ 修复用户 #{$user->id}: {$originalPhone} -> {$cleanPhone}");
                    $fixedCount++;
                } else {
                    $this->warn("⚠️  无法修复用户 #{$user->id}: {$originalPhone}");
                }
            } catch (\Exception $e) {
                $this->error("❌ 修复用户 #{$user->id} 失败: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $this->info("\n📊 修复完成:");
        $this->info("✅ 成功修复: {$fixedCount} 个");
        if ($errorCount > 0) {
            $this->warn("❌ 修复失败: {$errorCount} 个");
        }
        
        return 0;
    }
    
    /**
     * 清理手机号
     */
    private function cleanPhoneNumber(string $phone): ?string
    {
        // 移除所有非数字字符
        $digits = preg_replace('/\D/', '', $phone);
        
        // 检查长度是否合理
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null; // 无法修复
        }
        
        // 检查是否包含异常字符
        if (!preg_match('/^\d+$/', $digits)) {
            return null; // 无法修复
        }
        
        return $digits;
    }
}
