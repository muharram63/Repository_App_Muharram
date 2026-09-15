<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Описание компании в анкете работодателя валидируется до 600 символов,
 * а колонка была varchar(255). При строгом режиме MySQL сохранение текста
 * длиннее 255 падало с 22001 «Data too long» — работодатель видел пятисотую
 * вместо сохранённой анкеты. Расширяем колонку до text: это соответствует
 * правилу валидации и тому, как поле выглядит в форме.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        // текст длиннее 255 при откате обрезался бы молча — подрезаем явно
        DB::table('employers')->update([
            'description' => DB::raw('substr(description, 1, 255)'),
        ]);

        Schema::table('employers', function (Blueprint $table) {
            $table->string('description')->change();
        });
    }
};
