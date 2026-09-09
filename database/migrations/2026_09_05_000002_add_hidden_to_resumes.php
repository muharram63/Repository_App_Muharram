<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Модерация скрывает конкретное резюме, а не профиль соискателя целиком:
     * раньше жалоба на одно резюме прятала все его резюме и затирала
     * настройку приватности, выставленную самим пользователем.
     */
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->boolean('hidden')->default(false)->after('views');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('hidden');
        });
    }
};
