<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Каскадное удаление: резюме уходят вместе с анкетой соискателя,
     * вакансии — вместе с компанией. Раньше удаление падало с ошибкой FK.
     */
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropForeign(['applicant_id']);
            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
        });

        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropForeign(['employer_id']);
            $table->foreign('employer_id')->references('id')->on('employers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropForeign(['applicant_id']);
            $table->foreign('applicant_id')->references('id')->on('applicants');
        });

        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropForeign(['employer_id']);
            $table->foreign('employer_id')->references('id')->on('employers');
        });
    }
};
