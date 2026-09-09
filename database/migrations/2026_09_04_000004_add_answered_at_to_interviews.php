<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Отметка о том, что собеседник вошёл в комнату:
     * по ней отличаем состоявшийся звонок от пропущенного.
     */
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropColumn('answered_at');
        });
    }
};
