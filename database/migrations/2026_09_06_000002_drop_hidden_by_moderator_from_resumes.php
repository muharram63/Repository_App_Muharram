<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Колонка осталась без хозяина: администратор больше не прячет чужие резюме,
     * а соискателю такой переключатель не давали. Скрытое резюме вернуть было
     * бы некому. Убираем — «спрятать себя из каталога» уже есть в настройках
     * приватности (user_settings.hide_profile).
     */
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('hidden_by_moderator');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->boolean('hidden_by_moderator')->default(false)->after('views');
        });
    }
};
