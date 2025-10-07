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
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('phone_batch_id')->nullable()->constrained('phone_batches')->onDelete('set null')->comment('绑定的手机号批次ID');
            $table->boolean('auto_compare_enabled')->default(false)->comment('是否启用自动比对');
            $table->timestamp('last_sync_at')->nullable()->comment('最后同步时间');
            
            $table->index('phone_batch_id');
            $table->index('auto_compare_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['phone_batch_id']);
            $table->dropColumn(['phone_batch_id', 'auto_compare_enabled', 'last_sync_at']);
        });
    }
};
