<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * «hidden» — занятое имя: Eloquent объявляет protected $hidden в трейте
     * HidesAttributes (список полей, скрытых при сериализации). Из кода другой
     * модели $resume->hidden возвращал этот пустой массив вместо значения
     * колонки: protected виден между наследниками одного и того же Model,
     * поэтому __get и приведение типов не срабатывали. Переименовываем.
     */
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->renameColumn('hidden', 'hidden_by_moderator');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->renameColumn('hidden_by_moderator', 'hidden');
        });
    }
};
