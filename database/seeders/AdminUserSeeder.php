<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建默认管理员账号
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => '管理员',
                'password' => Hash::make('Aaa123123'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('默认管理员账号已创建：');
        $this->command->info('邮箱：admin@admin.com');
        $this->command->info('密码：Aaa123123');
    }
}
