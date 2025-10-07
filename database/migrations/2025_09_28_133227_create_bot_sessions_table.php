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
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade')->comment('关联机器人ID');
            $table->longText('session_data')->nullable()->comment('会话数据（JSON）');
            $table->text('qr_code')->nullable()->comment('QR 码数据');
            $table->timestamp('expires_at')->nullable()->comment('会话过期时间');
            $table->timestamps();
            
            // 索引
            $table->index('bot_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};
