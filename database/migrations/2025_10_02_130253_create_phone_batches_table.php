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
        // 创建批次主表
        Schema::create('phone_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('批次名称');
            $table->text('description')->nullable()->comment('批次描述');
            $table->integer('total_count')->default(0)->comment('总数量');
            $table->integer('processed_count')->default(0)->comment('已处理数量');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('状态');
            $table->timestamps();
            
            $table->index('status');
            $table->index('created_at');
        });

        // 创建手机号明细表
        Schema::create('phone_batch_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('phone_batch_id')->comment('批次ID');
            $table->string('phone_number', 50)->comment('手机号');
            $table->timestamps();
            
            $table->foreign('phone_batch_id')->references('id')->on('phone_batches')->onDelete('cascade');
            $table->index('phone_batch_id');
            $table->index('phone_number');
            $table->unique(['phone_batch_id', 'phone_number'], 'batch_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_batch_numbers');
        Schema::dropIfExists('phone_batches');
    }
};
