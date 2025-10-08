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
        Schema::create('whatsapp_users', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 50)->nullable()->comment('用户手机号（LID用户可能为空）');
            $table->string('whatsapp_user_id', 100)->nullable()->comment('WhatsApp用户ID（LID或手机号）');
            $table->string('jid', 200)->nullable()->comment('完整的JID（如：123456789@s.whatsapp.net）');
            $table->string('nickname')->nullable()->comment('用户昵称');
            $table->string('profile_picture', 500)->nullable()->comment('头像URL');
            $table->timestamps();
            
            // 索引
            $table->index('phone_number');
            $table->index('whatsapp_user_id');
            $table->index('jid');
            
            // 复合唯一索引：手机号或WhatsApp用户ID至少有一个不为空
            $table->unique(['phone_number', 'whatsapp_user_id'], 'unique_user_identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_users');
    }
};
