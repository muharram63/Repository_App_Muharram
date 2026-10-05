<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Когда запущен разбор документов.
 *
 * Зависание разбора раньше считалось по updated_at собеседования. Пока
 * кандидат ждал на странице документов, это работало. Теперь он сразу идёт в
 * разговор, а каждая оценка ответа обновляет строку собеседования — по
 * updated_at зависший разбор выглядел бы вечно свежим.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interviews', function (Blueprint $table) {
            $table->timestamp('analysis_started_at')->nullable()->after('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interviews', function (Blueprint $table) {
            $table->dropColumn('analysis_started_at');
        });
    }
};
