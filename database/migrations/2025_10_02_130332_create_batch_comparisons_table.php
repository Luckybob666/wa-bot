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
        Schema::create('batch_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_batch_id')->constrained('phone_batches')->onDelete('cascade')->comment('关联手机号批次ID');
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade')->comment('关联群组ID');
            $table->text('matched_numbers')->comment('已匹配的手机号（JSON格式）');
            $table->text('unmatched_numbers')->comment('未匹配的手机号（JSON格式）');
            $table->integer('matched_count')->default(0)->comment('匹配数量');
            $table->integer('unmatched_count')->default(0)->comment('未匹配数量');
            $table->decimal('match_rate', 5, 2)->default(0)->comment('匹配率（百分比）');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('比对状态');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            
            $table->index('phone_batch_id');
            $table->index('group_id');
            $table->index('status');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_comparisons');
    }
};
