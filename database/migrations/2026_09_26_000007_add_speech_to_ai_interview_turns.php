<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Озвучка реплик ИИ.
 *
 * Синтез речи занимает около четырёх секунд, поэтому вопрос показывается
 * текстом сразу, а звук догоняет: состояние хранится здесь, и страница по нему
 * понимает, ждать ли ей аудио или сразу читать вопрос браузерным синтезом.
 *
 * Путь к файлу, а не сам файл: WAV на семь секунд речи — это 340 КБ, в базе им
 * не место. Файл лежит на приватном диске и отдаётся с проверкой владельца.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interview_turns', function (Blueprint $table) {
            // none — озвучка не запрашивалась (реплики кандидата не озвучиваем);
            // pending — задача в очереди; ready — файл на месте;
            // failed — синтез не удался, страница перейдёт на браузерный голос
            $table->string('speech_status', 12)->default('none')->after('injection_flagged');
            $table->string('speech_path')->nullable()->after('speech_status');

            // Голос и длительность нужны для кэша и для интерфейса: смена голоса
            // в настройках должна сама отправить прежний файл в мусор, а
            // длительность позволяет показать полосу воспроизведения.
            $table->string('speech_voice', 40)->nullable()->after('speech_path');
            $table->unsignedInteger('speech_ms')->nullable()->after('speech_voice');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interview_turns', function (Blueprint $table) {
            $table->dropColumn(['speech_status', 'speech_path', 'speech_voice', 'speech_ms']);
        });
    }
};
