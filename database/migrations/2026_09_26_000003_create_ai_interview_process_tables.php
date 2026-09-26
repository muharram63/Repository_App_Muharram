<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ход собеседования: документы кандидата, реплики и матрица требований.
 *
 * Тексты документов и реплик шифруются средствами Laravel, поэтому колонки
 * longText, а не json: в базе лежит шифротекст — он длиннее исходного текста
 * и структурой json не является.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interview_id')->constrained('ai_interviews')->cascadeOnDelete();

            // diploma | certificate | other. Резюме здесь нет намеренно: оно
            // уже есть в таблице resumes, и просить загрузить его заново
            // значило бы не доверять данным собственной площадки.
            $table->string('kind', 16)->default('diploma');

            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();

            // Текст документа, его разбор и вердикт модели содержат имя,
            // учебное заведение и даты — всё это шифруется, поэтому longText:
            // шифротекст структурой json не является и в json-колонку MySQL
            // его не примет.
            $table->longText('extracted_text')->nullable();
            $table->longText('parsed')->nullable();
            $table->longText('analysis')->nullable();

            // Отпечаток файла и требований: пока они те же, разбор не
            // повторяем. Приём взят у разбора совпадения — match_analyses.source_hash.
            $table->string('source_hash', 32)->nullable();

            // pending | analysed | unreadable | failed
            $table->string('status', 16)->default('pending');
            $table->string('failure_reason')->nullable();
            $table->timestamp('analysed_at')->nullable();

            $table->timestamps();
            $table->index(['ai_interview_id', 'status']);
        });

        Schema::create('ai_interview_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interview_id')->constrained('ai_interviews')->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->string('role', 10);
            $table->longText('text');

            // technical | behavioral | situational | clarifying | follow_up
            $table->string('question_kind', 16)->nullable();
            $table->string('criterion_key', 40)->nullable();

            // Балл и обоснование кандидат не видит: они нужны решению и отчёту
            // работодателю. Оценку ставит отдельный вызов модели, поэтому она
            // может прийти позже самой реплики — отсюда nullable.
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedTinyInteger('max_score')->nullable();
            $table->text('rationale')->nullable();

            $table->boolean('injection_flagged')->default(false);

            $table->timestamps();
            $table->unique(['ai_interview_id', 'position']);
        });

        Schema::create('ai_requirement_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interview_id')->constrained('ai_interviews')->cascadeOnDelete();
            $table->foreignId('criterion_id')->nullable()
                ->constrained('ai_interview_criteria')->nullOnDelete();

            $table->string('requirement', 200);
            $table->string('kind', 8)->default('must');

            // found | partial | missing | confirmed_in_interview
            //
            // Статус меняется по ходу: документы дают исходный, интервью может
            // поднять «не найдено» до «подтверждено устно». Ворота решения
            // смотрят на финальный статус — иначе система отказывала бы всем,
            // у кого навык есть, но в резюме он не описан.
            $table->string('status', 24)->default('missing');

            // цитата-доказательство: без неё вердикт нечем проверить
            $table->text('evidence_quote')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();

            // documents | interview — чем именно требование закрыто
            $table->string('origin', 12)->default('documents');

            $table->timestamps();
            $table->index(['ai_interview_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requirement_checks');
        Schema::dropIfExists('ai_interview_turns');
        Schema::dropIfExists('ai_candidate_documents');
    }
};
