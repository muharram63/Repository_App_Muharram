<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Обращения пользователей к администрации. Отдельная таблица, а не
     * переиспользование complaints или messages: в раздел «Комментарии» админки
     * должно попадать только то, что человек осознанно отправил администратору.
     */
    public function up(): void
    {
        Schema::create('admin_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('topic', 32);
            $table->text('body');

            $table->enum('status', ['new', 'in_review', 'answered', 'closed'])->default('new');

            // кто из администраторов ответил
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_reply')->nullable();
            $table->timestamp('answered_at')->nullable();

            // автор открыл ответ
            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_comments');
    }
};
