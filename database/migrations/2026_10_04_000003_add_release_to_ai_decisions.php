<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Решение ИИ — рекомендация, пока его не отправили кандидату.
 *
 * Раньше решение уходило кандидату в ту же секунду, как его посчитал код.
 * Теперь ИИ готовит отчёт и спрашивает работодателя: тот подтверждает или
 * меняет решение, и только тогда кандидат получает ответ. В режиме «ИИ решает
 * сам» ответ уходит и без подтверждения — через 24 часа, как обещано
 * кандидату.
 *
 * released_at — когда ответ ушёл кандидату; release_source — кто отправил:
 * employer (работодатель подтвердил или изменил) или ai (срок вышел).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_decisions', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('requires_manual_review');
            $table->string('release_source', 12)->nullable()->after('released_at');
        });

        // прежние решения кандидаты уже получили — отмечаем их отправленными
        DB::table('ai_decisions')->whereNull('released_at')->update([
            'released_at' => DB::raw('created_at'),
            'release_source' => 'ai',
        ]);
    }

    public function down(): void
    {
        Schema::table('ai_decisions', function (Blueprint $table) {
            $table->dropColumn(['released_at', 'release_source']);
        });
    }
};
