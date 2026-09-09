<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Черновик удаляется сразу после публикации резюме, поэтому ссылка на
     * созданное резюме больше не заполняется и колонка осталась мёртвой.
     */
    public function up(): void
    {
        // Внешний ключ снимаем отдельным шагом и до колонки: иначе sqlite
        // пересобирает таблицу с ключом, ссылающимся на уже удалённое поле.
        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->dropForeign(['resume_id']);
        });

        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->dropColumn('resume_id');
        });
    }

    public function down(): void
    {
        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->foreignId('resume_id')->nullable()->constrained('resumes')->nullOnDelete();
        });
    }
};
