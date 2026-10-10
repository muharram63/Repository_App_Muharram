<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Тренажёр собеседований — платная тренировка для кандидатов.
 *
 * Только новые таблицы: существующие не меняются. Сама тренировка идёт на
 * готовом движке ИИ-собеседования — через скрытую учебную вакансию служебного
 * аккаунта, — а здесь лежит всё, что принадлежит только тренажёру:
 *
 * trainer_tracks      — направления (настраиваются в админке): название и
 *                       навыки, по которым ИИ спрашивает и проверяет;
 * trainer_sessions    — тренировки кандидата: направление, уровень, ссылка на
 *                       собеседование движка, итог и разбор для кандидата;
 * trainer_packages    — пакеты попыток с ценой в сомони;
 * trainer_payments    — заявки на оплату (ручной перевод, потом — банк);
 * trainer_ledger      — журнал попыток: каждое начисление и списание;
 * trainer_free_claims — кому уже дана бесплатная попытка (по email и телефону,
 *                       в виде хеша), чтобы её нельзя было получить повторно;
 * trainer_settings    — реквизиты для оплаты и прочие настройки админки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('description')->nullable();
            // навыки: [{label, kind: must|nice, weight}] — станут критериями оценки
            $table->json('skills');
            $table->string('language', 5)->default('ru');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('trainer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained('trainer_tracks')->nullOnDelete();
            // название направления на момент тренировки: направление могут переименовать
            $table->string('track_title', 120);
            $table->string('level', 10);
            $table->foreignId('vacancy_id')->nullable()->constrained('vacancies')->nullOnDelete();
            $table->foreignId('ai_interview_id')->nullable()->unique()->constrained('ai_interviews')->nullOnDelete();
            // active → finished; refunded — попытка возвращена (сбой по нашей вине)
            $table->string('status', 12)->default('active');
            $table->unsignedTinyInteger('score')->nullable();
            // разбор для кандидата: что хорошо, ошибки, как правильно, что подтянуть
            $table->json('feedback')->nullable();
            $table->string('feedback_status', 10)->default('none');
            $table->boolean('demo')->default(false);
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('trainer_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->unsignedSmallInteger('attempts');
            // цена в сомони, целым числом
            $table->unsignedInteger('price');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('trainer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('trainer_packages')->nullOnDelete();
            // на момент заявки: пакет могут изменить, а заявка — нет
            $table->string('package_title', 120);
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('amount');
            // способ оплаты: manual — перевод по реквизитам; потом банк
            $table->string('gateway', 20)->default('manual');
            // pending — создана; claimed — «Я оплатил»; confirmed / rejected — решение админа
            $table->string('status', 12)->default('pending');
            $table->string('payer_note', 255)->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('trainer_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // +1 начисление, -1 списание
            $table->integer('delta');
            // free, purchase, manual, start, refund
            $table->string('reason', 12);
            $table->foreignId('payment_id')->nullable()->constrained('trainer_payments')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('trainer_sessions')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
            // одна попытка не возвращается дважды
            $table->unique(['session_id', 'reason']);
        });

        Schema::create('trainer_free_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // хеши, а не сами email и телефон: хранить их второй раз незачем
            $table->string('email_hash', 64)->nullable()->unique();
            $table->string('phone_hash', 64)->nullable()->unique();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('trainer_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_settings');
        Schema::dropIfExists('trainer_free_claims');
        Schema::dropIfExists('trainer_ledger');
        Schema::dropIfExists('trainer_payments');
        Schema::dropIfExists('trainer_packages');
        Schema::dropIfExists('trainer_sessions');
        Schema::dropIfExists('trainer_tracks');
    }
};
