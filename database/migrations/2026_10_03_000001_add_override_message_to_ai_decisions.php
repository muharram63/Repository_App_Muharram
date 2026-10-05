<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Письмо кандидату после того, как решение изменил работодатель.
 *
 * Раньше переопределение меняло только исход: кандидат видел метку «Прошёл
 * отбор», а под ней — письмо-отказ, которое ИИ написал раньше (и наоборот).
 * Новое письмо храним отдельно: прежнее остаётся в отчёте как есть — что
 * сказали человеку, должно быть воспроизводимо.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_decisions', function (Blueprint $table) {
            // шифруется, как и само письмо: в нём причины отказа
            $table->text('override_message')->nullable()->after('override_note');
        });
    }

    public function down(): void
    {
        Schema::table('ai_decisions', function (Blueprint $table) {
            $table->dropColumn('override_message');
        });
    }
};
