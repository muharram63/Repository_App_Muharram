<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Ещё больше вопросов и срок ответа «24 часа» по умолчанию.
 *
 * Вопросов теперь не меньше восьми, по умолчанию двенадцать
 * (AiInterviewConfig::MIN_QUESTIONS / DEFAULT_QUESTIONS). Срок ответа Анна
 * произносит в конце разговора: «ответ вы получите в течение 24 часов».
 * Сроки, которые работодатели уже задали сами, не трогаем.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interview_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('questions_count')->default(12)->change();
            $table->string('response_sla', 120)->default('в течение 24 часов')->change();
        });

        DB::table('ai_interview_configs')
            ->where('questions_count', '<', 12)
            ->update(['questions_count' => 12]);
    }

    public function down(): void
    {
        Schema::table('ai_interview_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('questions_count')->default(10)->change();
            $table->string('response_sla', 120)->default('в течение 3 дней')->change();
        });
    }
};
