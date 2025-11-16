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
            $table->string('whatsapp_user_id', 200)->nullable()->comment('WhatsApp用户ID（participants中的id字段，如：148932587991082@lid）');
            $table->string('lid', 200)->nullable()->comment('用户的LID（participants中的lid字段，用于退群时查找）');
            $table->string('jid', 200)->nullable()->comment('完整的JID（如：123456789@s.whatsapp.net，LID用户可能为空）');
            $table->string('nickname')->nullable()->comment('用户昵称');
            $table->string('profile_picture', 500)->nullable()->comment('头像URL');
            
            // 群组相关字段（同一个用户可以进入多个群组，所以每条记录代表一个用户在某个群组中的状态）
            $table->foreignId('group_id')->constrained('whatsapp_groups')->onDelete('cascade')->comment('关联群ID');
            $table->unsignedBigInteger('bot_id')->nullable()->comment('所属机器人ID');
            $table->timestamp('left_at')->nullable()->comment('退出时间');
            $table->boolean('is_active')->default(true)->comment('用户状态（true=在群里，false=退群或被移除）');
            $table->boolean('is_admin')->default(false)->comment('是否为群管理员');
            $table->boolean('removed_by_admin')->default(false)->comment('是否被管理员移除');
            
            $table->timestamps();
            
            // 索引
            $table->index('phone_number');
            $table->index('whatsapp_user_id');
            $table->index('lid');
            $table->index('jid');
            $table->index('group_id');
            $table->index('bot_id');
            $table->index('left_at');
            $table->index('is_active');
            $table->index('removed_by_admin');
            $table->index('created_at');
            
            // 唯一约束：同一个用户在同一个群组中只能有一条记录（使用 whatsapp_user_id 或 lid）
            $table->unique(['group_id', 'whatsapp_user_id'], 'unique_group_user');
            
            // 外键约束
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('set null');
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
