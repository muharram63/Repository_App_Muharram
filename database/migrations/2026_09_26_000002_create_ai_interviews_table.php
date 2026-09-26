<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Собеседование кандидата с ИИ — экземпляр воронки.
 *
 * Префикс ai_ в имени обязателен: таблица interviews в проекте уже занята
 * видеовстречами Jitsi, и совпадение имён сломало бы существующие звонки.
 *
 * Отклик хранится ссылкой, а не владельцем записи: кандидат может отозвать
 * отклик, но пройденное собеседование и его аудит должны остаться.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('vacancy_response_id')->nullable()
                ->constrained('vacancy_responses')->nullOnDelete();

            // consent -> documents -> interview -> test -> decision -> done
            $table->string('stage', 20)->default('consent');

            // Без согласия процесс не начинается, поэтому время и адрес
            // фиксируются: это доказательство, что кандидата предупредили.
            $table->timestamp('consent_at')->nullable();
            // адрес — тоже персональные данные, поэтому колонка под шифротекст,
            // а не под 45 символов «сырого» IPv6
            $table->string('consent_ip')->nullable();

            $table->unsignedTinyInteger('documents_score')->nullable();
            $table->unsignedTinyInteger('interview_score')->nullable();
            $table->unsignedTinyInteger('test_score')->nullable();
            $table->unsignedTinyInteger('total_score')->nullable();

            // passed | rejected | manual_review; null — решение ещё не принято
            $table->string('outcome', 20)->nullable();
            $table->boolean('requires_review')->default(false);

            // Сколько пограничных дораундов уже было. Без счётчика система
            // могла бы уточнять бесконечно, так и не приняв решение.
            $table->unsignedTinyInteger('follow_up_round')->default(0);

            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // одно собеседование на пару «вакансия + кандидат»
            $table->unique(['vacancy_id', 'applicant_id']);
            $table->index(['vacancy_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interviews');
    }
};
