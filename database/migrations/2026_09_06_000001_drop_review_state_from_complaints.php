<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Разбор жалоб — не забота администратора. Собственную очередь модератора
     * (review_state и reviewed_at) убираем: статус жалобы ведёт владелец объекта,
     * а администратору остаются просмотр, блокировка и удаление.
     * Историю действий это не трогает — она лежит в complaint_actions.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['review_state']);
            $table->dropColumn(['review_state', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('review_state', ['new', 'in_progress', 'handled', 'dismissed'])
                ->default('new')
                ->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('review_state');

            $table->index('review_state');
        });
    }
};
