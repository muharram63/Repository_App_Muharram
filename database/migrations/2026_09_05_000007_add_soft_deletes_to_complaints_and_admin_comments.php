<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Суточный лимит считал существующие строки, а отзыв удалял их физически:
     * отправил десять, отозвал, отправил ещё десять. Мягкое удаление оставляет
     * запись для подсчёта, но убирает её из всех списков.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('admin_comments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('admin_comments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
