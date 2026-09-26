<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тестовое задание и решение кандидата.
 *
 * Эталонное решение и критерии проверки шифруются и наружу не отдаются
 * никогда: увидев их, кандидат проверял бы не свои знания, а память.
 * По этой же причине они лежат в longText — в базе шифротекст.
 *
 * Заданий на одно собеседование может быть больше одного: пограничный балл
 * приводит к короткому дополнительному заданию, и оно не должно затирать
 * основное, иначе в отчёте не останется следа, за что поставлен итог.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_test_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interview_id')->constrained('ai_interviews')->cascadeOnDelete();

            // main | follow_up
            $table->string('kind', 12)->default('main');

            $table->longText('statement');

            // code | case | writing | calculation — от формата зависит и проверка:
            // код можно исполнить, кейс оценивает только модель по рубрике
            $table->string('format', 16)->default('case');
            // язык программирования, если формат code
            $table->string('language', 30)->nullable();

            $table->longText('reference_solution')->nullable();
            $table->longText('rubric')->nullable();

            $table->unsignedSmallInteger('time_limit_minutes')->default(60);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('deadline_at')->nullable();

            $table->timestamps();
            $table->index(['ai_interview_id', 'kind']);
        });

        Schema::create('ai_test_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_test_task_id')->constrained('ai_test_tasks')->cascadeOnDelete();

            $table->longText('content')->nullable();
            $table->json('files')->nullable();

            // Результат реального исполнения кода: код возврата, вывод, какие
            // тесты прошли. Хранится отдельно от мнения модели намеренно —
            // это факт, и в спорном случае он весомее любой оценки.
            $table->json('execution')->nullable();

            $table->unsignedTinyInteger('score')->nullable();
            // разбор цитирует решение кандидата — шифруется, отсюда longText
            $table->longText('review')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_test_submissions');
        Schema::dropIfExists('ai_test_tasks');
    }
};
