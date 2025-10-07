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
        Schema::create('group_whatsapp_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade')->comment('关联群ID');
            $table->foreignId('whatsapp_user_id')->constrained('whatsapp_users')->onDelete('cascade')->comment('关联WhatsApp用户ID');
            $table->timestamp('joined_at')->comment('加入时间');
            $table->timestamp('left_at')->nullable()->comment('退出时间（未退出为 NULL）');
            $table->boolean('is_admin')->default(false)->comment('是否为群管理员');
            $table->timestamps();
            
            // 索引
            $table->index('group_id');
            $table->index('whatsapp_user_id');
            $table->index('joined_at');
            $table->index('left_at');
            $table->unique(['group_id', 'whatsapp_user_id']); // 用户在同一群中只能有一条记录
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_whatsapp_user');
    }
};
