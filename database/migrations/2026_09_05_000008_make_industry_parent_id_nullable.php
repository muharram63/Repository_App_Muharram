<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * industries.parent_id — обязательное целое без внешнего ключа, которое
     * ни на что не влияет: администратора заставляли выдумывать число.
     * Делаем поле необязательным. Превращать его в настоящую ссылку на
     * родительскую индустрию — отдельное решение: сейчас 10 из 17 значений
     * не соответствуют ни одной существующей записи.
     */
    public function up(): void
    {
        Schema::table('industries', function (Blueprint $table) {
            $table->integer('parent_id')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('industries', function (Blueprint $table) {
            $table->integer('parent_id')->nullable(false)->change();
        });
    }
};
