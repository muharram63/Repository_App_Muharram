<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Какая модель поставила балл за ответ.
 *
 * Когда суточный лимит основной модели оценки исчерпан, оценка уходит на
 * запасную lite. Это честнее, чем оставить ответ без балла, но работодатель
 * должен видеть, что часть ответов оценивала менее глубокая модель, — иначе
 * отчёт молчал бы о том, кто на самом деле ставил балл.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interview_turns', function (Blueprint $table) {
            $table->string('scored_by', 60)->nullable()->after('rationale');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interview_turns', function (Blueprint $table) {
            $table->dropColumn('scored_by');
        });
    }
};
