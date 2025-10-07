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
        Schema::create('group_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade')->comment('关联机器人ID');
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade')->comment('关联群ID');
            $table->foreignId('whatsapp_user_id')->nullable()->constrained('whatsapp_users')->onDelete('set null')->comment('关联WhatsApp用户ID（可为空）');
            $table->enum('event_type', ['member_joined', 'member_left', 'group_updated'])->comment('事件类型');
            $table->json('event_data')->nullable()->comment('事件详细数据');
            $table->timestamp('created_at')->comment('事件发生时间');
            
            // 索引
            $table->index('bot_id');
            $table->index('group_id');
            $table->index('whatsapp_user_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_events');
    }
};
