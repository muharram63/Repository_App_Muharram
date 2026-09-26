<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Настройка ИИ-собеседования у вакансии.
 *
 * Отдельная таблица, а не новые колонки в vacancies: вакансия — сущность
 * старше модуля, и её схему трогать нельзя, иначе откат модуля потребовал бы
 * править уже работающую часть площадки. Связь один-к-одному держит unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interview_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->unique()->constrained('vacancies')->cascadeOnDelete();

            // выключено по умолчанию: включение — осознанное действие работодателя,
            // иначе модуль начал бы собеседовать кандидатов на старых вакансиях
            $table->boolean('enabled')->default(false);

            $table->string('level', 20)->default('middle');
            // язык разговора — из тех, что уже поддерживает интерфейс
            $table->string('language', 5)->default('ru');
            $table->unsignedTinyInteger('questions_count')->default(8);

            // Веса в процентах, сумма = 100. Проверку суммы делает приложение:
            // в базе такое условие пришлось бы описывать дважды — тесты идут
            // на sqlite, а боевая база MySQL.
            $table->unsignedTinyInteger('weight_documents')->default(30);
            $table->unsignedTinyInteger('weight_interview')->default(45);
            $table->unsignedTinyInteger('weight_test')->default(25);

            // Два порога, между ними — пограничная зона, где решение не
            // принимается сразу, а задаются дополнительные вопросы.
            $table->unsignedTinyInteger('threshold_reject')->default(45);
            $table->unsignedTinyInteger('threshold_accept')->default(70);

            $table->unsignedSmallInteger('test_time_limit_minutes')->default(60);

            // Срок ответа кандидату — словами, как его сформулировал
            // работодатель: «в течение 3 дней», «в течение нескольких часов».
            $table->string('response_sla', 120)->default('в течение 3 дней');

            // auto — ИИ сам ставит статус отклика; advisory — только отчёт,
            // решение остаётся за человеком. Предохранитель на случай, когда
            // работодатель ещё не доверяет автоматике.
            $table->string('decision_mode', 10)->default('auto');

            $table->timestamps();
        });

        Schema::create('ai_interview_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();

            // key — устойчивый слаг: по нему оценки реплик связываются с
            // критерием, и переименование подписи не рвёт проставленные баллы
            $table->string('key', 40);
            $table->string('label', 120);
            $table->text('description')->nullable();

            // must — обязательное требование, работает как ворота в решении;
            // nice — желательное, влияет только на балл
            $table->string('kind', 8)->default('must');
            $table->unsignedTinyInteger('weight')->default(10);

            // ai — критерий предложила модель, employer — добавил человек.
            // Пока не подтверждён работодателем, в оценке не участвует.
            $table->string('source', 10)->default('ai');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedTinyInteger('position')->default(0);

            $table->timestamps();
            $table->unique(['vacancy_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interview_criteria');
        Schema::dropIfExists('ai_interview_configs');
    }
};
