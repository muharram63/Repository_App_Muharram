<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Черновики становятся историей: у соискателя их может быть много, как
     * переписок в чате. Прежнее ограничение допускало ровно один, поэтому
     * начать новое резюме можно было только стерев предыдущее.
     */
    public function up(): void
    {
        // Внешний ключ снимаем первым: MySQL держится за уникальный индекс,
        // пока тот обслуживает связь, и удалить его не даёт.
        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->dropForeign(['applicant_id']);
        });

        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->dropUnique('resume_drafts_applicant_id_unique');
        });

        Schema::table('resume_drafts', function (Blueprint $table) {
            // связь возвращаем уже без требования единственности
            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
            // резюме, выросшее из разговора: по нему видно, что он завершён
            $table->foreignId('resume_id')->nullable()->after('stage')
                ->constrained('resumes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->dropForeign(['resume_id']);
            $table->dropForeign(['applicant_id']);
        });

        Schema::table('resume_drafts', function (Blueprint $table) {
            $table->dropColumn('resume_id');
            $table->unique('applicant_id');
            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
        });
    }
};
