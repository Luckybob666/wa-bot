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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade')->comment('关联机器人ID');
            $table->string('group_id', 50)->comment('WhatsApp 群 ID');
            $table->string('name')->comment('群名称');
            $table->text('description')->nullable()->comment('群描述');
            $table->integer('member_count')->default(0)->comment('当前成员数量');
            $table->timestamps();
            
            // 索引
            $table->index('bot_id');
            $table->index('group_id');
            $table->unique(['bot_id', 'group_id']); // 同一机器人下的群ID唯一
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
