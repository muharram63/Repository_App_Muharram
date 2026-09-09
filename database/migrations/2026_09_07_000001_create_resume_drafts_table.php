<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Черновик диалога с помощником. Состояние живёт в базе, а не в сессии:
     * разговор длинный, и обновление страницы не должно стирать собранное.
     */
    public function up(): void
    {
        Schema::create('resume_drafts', function (Blueprint $table) {
            $table->id();
            // один активный черновик на соискателя: «начать заново» его очищает
            $table->foreignId('applicant_id')->unique()->constrained('applicants')->cascadeOnDelete();
            // история диалога для модели: [{role: user|model, text: ...}]
            $table->json('messages');
            // накопленное состояние резюме — источник правды для превью
            $table->json('data');
            $table->string('stage', 24)->default('identity');
            // причёсанная версия и поля, которые уйдут в резюме
            $table->json('final')->nullable();
            // резюме, созданное из этого черновика
            $table->foreignId('resume_id')->nullable()->constrained('resumes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_drafts');
    }
};
