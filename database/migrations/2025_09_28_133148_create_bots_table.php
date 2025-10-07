<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('机器人名称');
            $table->string('phone_number', 50)->nullable()->unique()->comment('WhatsApp 手机号');
            $table->enum('status', ['offline', 'connecting', 'online', 'error'])->default('offline')->comment('登录状态');
            $table->longText('session_data')->nullable()->comment('WhatsApp 会话数据（JSON）');
            $table->timestamp('last_seen')->nullable()->comment('最后活跃时间');
            $table->timestamps();
            
            // 索引
            $table->index('status');
            $table->index('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
