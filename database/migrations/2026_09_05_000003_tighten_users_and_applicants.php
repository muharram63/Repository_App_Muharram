<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Две вещи из аудита:
     * 1) applicants.user_id без уникального индекса, хотя User::applicant() — это hasOne;
     * 2) users.password_confirmation — колонка под пароль в открытом виде,
     *    каст hashed на неё не действует, значение туда никогда не пишется.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password_confirmation')->nullable()->after('password');
        });
    }
};
