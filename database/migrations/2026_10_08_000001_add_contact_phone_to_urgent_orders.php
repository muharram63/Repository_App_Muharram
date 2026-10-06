<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Телефон заказчика для выбранного мастера.
 *
 * Готовый чат сайта связывает только компанию и соискателя, а заказчиком
 * бывает и частный человек без компании. Поэтому после выбора исполнителя
 * стороны обмениваются телефонами: мастеру открываются адрес и этот номер.
 * У пользователя своего телефона нет — он в анкете соискателя или компании,
 * отсюда отдельное поле; для старых заявок номер берётся из анкеты.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urgent_orders', function (Blueprint $table) {
            $table->string('contact_phone', 30)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('urgent_orders', function (Blueprint $table) {
            $table->dropColumn('contact_phone');
        });
    }
};
