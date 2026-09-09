<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Проверка навыков делом.
     *
     * Смысл всей затеи: работодатель видит не «5 лет опыта» со слов кандидата,
     * а результат одинакового для всех задания. Человеку без диплома это даёт
     * способ доказать навык, а компании — проверяемую цифру.
     */
    public function up(): void
    {
        // справочник навыков: по нему кандидат выбирает, что проверять
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('area', 60)->nullable();
            $table->timestamps();
        });

        // Банк заданий. Задание генерируется один раз и служит всем: платить
        // за генерацию на каждую попытку было бы разорительно и бессмысленно.
        Schema::create('skill_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->string('level', 20);
            // несколько вариантов одного уровня: одинаковое задание для всех
            // рано или поздно разошлось бы с ответами
            $table->unsignedTinyInteger('variant')->default(1);
            $table->json('questions');
            $table->timestamps();
            $table->unique(['skill_id', 'level', 'variant']);
        });

        Schema::create('skill_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('skill_test_id')->constrained('skill_tests')->cascadeOnDelete();
            $table->json('answers');
            // разбор по вопросам: что зачтено, что нет и почему
            $table->json('review')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['applicant_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_attempts');
        Schema::dropIfExists('skill_tests');
        Schema::dropIfExists('skills');
    }
};
