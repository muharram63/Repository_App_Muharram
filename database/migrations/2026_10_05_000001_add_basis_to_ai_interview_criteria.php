<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * На чём из вакансии основан критерий.
 *
 * Критерии, предложенные ИИ, путали работодателя: у вакансии «programming»
 * с навыками PHP и SQL единственным обязательным критерием вышло «Знание
 * дополнительных иностранных языков». Модель строила критерии по самому
 * заполненному полю, а работодатель не видел, откуда что взялось.
 *
 * basis — поле вакансии, из которого вырос критерий: title, skills,
 * description, experience, languages, schedule. Его видно рядом с критерием,
 * и по нему же код следит за порядком важности: профессия прежде языков.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interview_criteria', function (Blueprint $table) {
            $table->string('basis', 20)->nullable()->after('source');
            // цитата из вакансии, на которой критерий основан: «PHP, SQL»
            $table->string('basis_quote', 160)->nullable()->after('basis');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interview_criteria', function (Blueprint $table) {
            $table->dropColumn(['basis', 'basis_quote']);
        });
    }
};
