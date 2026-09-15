<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Требование к языкам у вакансии.
 *
 * До сих пор его нигде не хранили: разбор совпадения вытаскивал языки из
 * текста описания, а работодатель не мог указать их явно. Теперь это
 * отдельное поле — такое же перечисление через запятую, как навыки в
 * вакансии и языки в резюме, чтобы стороны сравнивались одинаково.
 *
 * Поле необязательное: многим вакансиям язык не важен, и пустое значение
 * означает «не требуется», а не «данных нет».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->string('languages')->nullable()->after('skill');
        });
    }

    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn('languages');
        });
    }
};
