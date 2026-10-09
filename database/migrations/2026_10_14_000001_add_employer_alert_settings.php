<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Уведомления работодателю о кандидатах: что присылать и куда.
 *
 * Три вида — новый отклик, готовый отчёт ИИ-собеседования, напоминание о
 * кандидатах, ждущих решения, — и три канала: на сайте (колокольчик и
 * всплывающее окно), в браузере (системное уведомление, когда вкладка
 * свёрнута) и на почту. Всё включено по умолчанию: пропустить кандидата хуже,
 * чем получить лишнее уведомление; выключить можно в настройках кабинета.
 *
 * notification_log — защита от дублей: одно событие — одно уведомление.
 * Уникальный ключ (кому + что) вставляется до отправки, и второй раз то же
 * событие не пройдёт, даже если два запроса придут одновременно.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->boolean('notify_new_candidates')->default(true);
            $table->boolean('notify_reports')->default(true);
            $table->boolean('notify_reminders')->default(true);
            $table->boolean('channel_site')->default(true);
            $table->boolean('channel_browser')->default(true);
            $table->boolean('channel_email')->default(true);
        });

        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 191);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');

        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn([
                'notify_new_candidates', 'notify_reports', 'notify_reminders',
                'channel_site', 'channel_browser', 'channel_email',
            ]);
        });
    }
};
