<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Запуск кода из ответа кандидата.
 *
 * Если кандидат отвечает Анне кодом, оценщик теперь запускает его, а не
 * только читает. Что запускалось и что вышло, нужно работодателю — иначе
 * балл за такой ответ ничем не отличался бы от балла «на глаз». Хранится
 * зашифрованным, как и сам ответ: это слова кандидата.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interview_turns', function (Blueprint $table) {
            $table->longText('code_check')->nullable()->after('rationale');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interview_turns', function (Blueprint $table) {
            $table->dropColumn('code_check');
        });
    }
};
