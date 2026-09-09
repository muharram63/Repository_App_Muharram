<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Разбор совпадения кандидата и вакансии.
     *
     * Считать его на каждый показ страницы дорого и медленно, поэтому пара
     * «вакансия + резюме» хранится вместе с отпечатком исходных текстов:
     * пока ни вакансию, ни резюме не правили, отдаём сохранённый разбор.
     */
    public function up(): void
    {
        Schema::create('match_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained('resumes')->cascadeOnDelete();
            // отпечаток текстов: изменились — разбор устарел
            $table->string('source_hash', 32);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['vacancy_id', 'resume_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_analyses');
    }
};
