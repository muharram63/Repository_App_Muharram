<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Уведомления владельцу объекта о мерах модератора.
     * Кто пожаловался — не раскрываем, только само действие и причина.
     */
    public function up(): void
    {
        Schema::create('moderation_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('complaint_id')->nullable()->constrained('complaints')->nullOnDelete();

            $table->string('action', 32);
            $table->string('subject');
            $table->string('reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_notices');
    }
};
