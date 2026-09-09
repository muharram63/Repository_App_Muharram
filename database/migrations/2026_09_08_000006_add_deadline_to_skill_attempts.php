<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Срок попытки хранится, а не вычисляется от времени создания: иначе
     * изменение лимита задним числом переписало бы правила уже начатых
     * и давно пройденных заданий.
     */
    public function up(): void
    {
        Schema::table('skill_attempts', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        Schema::table('skill_attempts', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
