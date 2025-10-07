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
        Schema::create('phone_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('批次名称');
            $table->text('description')->nullable()->comment('批次描述');
            $table->text('phone_numbers')->comment('手机号列表（JSON格式）');
            $table->integer('total_count')->default(0)->comment('总数量');
            $table->integer('processed_count')->default(0)->comment('已处理数量');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('状态');
            $table->timestamps();
            
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_batches');
    }
};
