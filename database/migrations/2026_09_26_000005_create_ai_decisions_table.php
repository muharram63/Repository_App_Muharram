<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Решение по кандидату — то, за что модуль отвечает перед человеком.
 *
 * Запись на каждый расчёт, а не одна на собеседование: пограничный балл
 * приводит к дораунду и пересчёту, и обе версии должны остаться. Иначе на
 * вопрос «почему сначала было иначе» ответить нечем.
 *
 * Баллы дублируются здесь, хотя есть и в ai_interviews: там — текущее
 * состояние, здесь — снимок на момент решения. Последующая правка настроек
 * вакансии не должна задним числом менять уже объявленный кандидату итог.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interview_id')->constrained('ai_interviews')->cascadeOnDelete();

            $table->unsignedTinyInteger('round')->default(1);

            $table->unsignedTinyInteger('documents_score')->nullable();
            $table->unsignedTinyInteger('interview_score')->nullable();
            $table->unsignedTinyInteger('test_score')->nullable();
            $table->unsignedTinyInteger('total')->nullable();

            // passed | rejected | manual_review
            $table->string('outcome', 20);

            // Что именно решило исход: missing_must_have, below_reject_threshold,
            // above_accept_threshold, borderline, invalid_ai_response,
            // empty_documents. Без этого поля отчёт превращается в «так решил ИИ».
            $table->string('gate_reason', 40)->nullable();
            // в обоснование попадают цитаты из резюме и ответов — шифруется
            $table->longText('reasons')->nullable();

            // Текст, который увидел кандидат. Храним именно отправленное:
            // формулировки промпта со временем меняются, а сказанное человеку
            // должно быть воспроизводимо дословно.
            $table->text('message_to_candidate')->nullable();

            $table->boolean('requires_manual_review')->default(false);

            // Работодатель вправе не согласиться с ИИ. Переопределение
            // дописывается к решению, а не заменяет его: видно и что решил ИИ,
            // и что решил человек.
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->string('override_outcome', 20)->nullable();
            $table->text('override_note')->nullable();

            $table->timestamps();
            $table->unique(['ai_interview_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_decisions');
    }
};
