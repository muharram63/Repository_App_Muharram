<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * В схеме у date_register был вшит день запуска миграции,
     * поэтому все новые пользователи получали одну и ту же дату регистрации.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_register')->nullable()->default(null)->change();
        });

        // приводим дату регистрации к моменту создания аккаунта
        DB::statement('update users set date_register = date(created_at) where created_at is not null');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_register')->nullable()->default(date('Y-m-d'))->change();
        });
    }
};
