<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Аудит модуля: каждое обращение к модели и каждая попытка ей манипулировать.
 *
 * Смысл таблицы ai_interactions в том, чтобы любое решение можно было
 * объяснить и перепроверить через месяцы: видно, что именно спросили, что
 * ответила модель, какой моделью и сколько это стоило. Промпт и ответ
 * шифруются — в них попадают данные кандидата.
 *
 * Собеседование указывается необязательно: критерии вакансии модель
 * предлагает до того, как появится первый кандидат.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interview_id')->nullable()
                ->constrained('ai_interviews')->cascadeOnDelete();
            $table->foreignId('vacancy_id')->nullable()
                ->constrained('vacancies')->cascadeOnDelete();

            // criteria_advice | resume_parse | document_audit | interview_question
            // | answer_score | task_compose | task_grade | decision_summary
            $table->string('purpose', 40);
            $table->string('model', 60);

            $table->longText('request')->nullable();
            $table->longText('response')->nullable();

            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            // ok | invalid | failed. invalid — ответ пришёл, но не прошёл
            // проверку схемой; именно он ведёт к статусу «ручная проверка».
            $table->string('status', 12)->default('ok');
            $table->string('error')->nullable();

            $table->timestamps();
            $table->index(['ai_interview_id', 'purpose']);
            $table->index('created_at');
        });

        Schema::create('ai_injection_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interview_id')->constrained('ai_interviews')->cascadeOnDelete();
            $table->foreignId('ai_interview_turn_id')->nullable()
                ->constrained('ai_interview_turns')->nullOnDelete();

            // Отрывок, на котором сработало правило. Целиком реплику здесь не
            // держим — она и так лежит в ai_interview_turns. Тип text, а не
            // string: слова кандидата шифруются и в 500 символов не уложатся.
            $table->text('snippet');
            $table->string('pattern', 60)->nullable();

            // Кандидата за попытку не наказываем: разговор просто возвращается
            // к вопросу. Поле хранит, что было сделано.
            $table->string('action', 24)->default('ignored');

            $table->timestamps();
            $table->index('ai_interview_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_injection_attempts');
        Schema::dropIfExists('ai_interactions');
    }
};
