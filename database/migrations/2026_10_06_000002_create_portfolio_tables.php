<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Портфолио мастера: выполненные работы с фото и видео.
 *
 * Маляр, сантехник, автомеханик не покажут диплом — они показывают сделанное.
 * Одна работа — одна карточка: название, вид работ, описание и несколько
 * файлов, при желании с отметкой «до» и «после».
 *
 * Файлы лежат на диске public в portfolio/{applicant}/{work}/: это открытая
 * витрина мастера, видео отдаётся напрямую и перематывается. По папке
 * соискателя файлы удаляются вместе с аккаунтом.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            // категорию из справочника не удаляют, но если удалят — работа останется
            $table->foreignId('work_category_id')->nullable()->constrained('work_categories')->nullOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            // когда выполнена — по желанию мастера
            $table->date('done_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['applicant_id', 'position']);
        });

        Schema::create('portfolio_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_work_id')->constrained('portfolio_works')->cascadeOnDelete();
            // photo | video
            $table->string('type', 8);
            // null — просто снимок; before | after — пара «до / после»
            $table->string('stage', 8)->nullable();
            $table->string('path');
            // уменьшенная копия фото для сетки
            $table->string('thumb_path')->nullable();
            // кадр-обложка видео, снятый в браузере при загрузке
            $table->string('poster_path')->nullable();
            $table->string('mime', 60);
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['portfolio_work_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_media');
        Schema::dropIfExists('portfolio_works');
    }
};
