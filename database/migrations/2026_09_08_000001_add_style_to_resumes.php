<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Оформление резюме, выбранное соискателем. Хранится рядом с самим резюме,
     * иначе выбор жил бы только в браузере и терялся на другом устройстве.
     */
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->string('style', 20)->default('classic')->after('documents');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
