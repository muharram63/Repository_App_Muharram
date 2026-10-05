<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Больше вопросов на ИИ-собеседовании.
 *
 * По умолчанию стояло 5, а работодатель мог поставить и 3. Первый вопрос —
 * знакомство, ещё один-два уходят на уточнения, и на сами требования вакансии
 * оставалось два-три вопроса: оценке не на чем было стоять. Теперь не меньше
 * шести (AiInterviewConfig::MIN_QUESTIONS), по умолчанию десять.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interview_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('questions_count')->default(10)->change();
        });

        // прежние настройки ниже минимума форма теперь не сохранит — поднимаем
        DB::table('ai_interview_configs')
            ->where('questions_count', '<', 6)
            ->update(['questions_count' => 10]);
    }

    public function down(): void
    {
        Schema::table('ai_interview_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('questions_count')->default(5)->change();
        });
    }
};
