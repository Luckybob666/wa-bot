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
        Schema::create('whatsapp_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade')->comment('关联机器人ID');
            $table->string('group_id', 50)->comment('WhatsApp 群 ID');
            $table->string('name')->comment('群名称');
            $table->text('description')->nullable()->comment('群描述');
            $table->integer('member_count')->default(0)->comment('当前成员数量');
            $table->enum('status', ['active', 'removed'])->default('active')->comment('群状态（活跃/已退出）');
            
            // 批次绑定和比对字段
            // 注意：phone_batch_id 字段先创建，外键约束在 phone_batches 表创建后通过单独的迁移添加
            $table->unsignedBigInteger('phone_batch_id')->nullable()->comment('绑定的手机号批次ID');
            $table->integer('matched_count')->default(0)->comment('批次中已进群数量');
            $table->integer('unmatched_count')->default(0)->comment('批次中未进群数量');
            $table->integer('extra_count')->default(0)->comment('群里多出的号码数量');
            $table->decimal('match_rate', 5, 2)->default(0)->comment('匹配率（百分比）');
            
            // 索引
            $table->index('bot_id');
            $table->index('group_id');
            $table->index('status');
            $table->index('phone_batch_id');
            $table->unique(['bot_id', 'group_id']); // 同一机器人下的群ID唯一
            
            // 时间戳字段放在最后
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_groups');
    }
};
