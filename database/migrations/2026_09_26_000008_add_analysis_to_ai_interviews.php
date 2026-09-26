<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Итог разбора документов по собеседованию.
 *
 * Здесь лежит то, что не укладывается в отдельные таблицы: структурированное
 * резюме, найденные несостыковки и выросшие из них уточняющие вопросы. Статусы
 * требований живут в ai_requirement_checks, вердикты по дипломам — в
 * ai_candidate_documents, а это — общая картина по кандидату.
 *
 * Разобранное резюме хранится у собеседования, а не у резюме, хотя и выглядит
 * его свойством. Причина в том, что резюме кандидат правит когда хочет, а
 * решение принимается по той версии, которая была на момент разбора: иначе
 * правка резюме задним числом меняла бы основание уже объявленного отказа.
 *
 * Колонка longText, а не json: содержимое шифруется, в базе шифротекст.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interviews', function (Blueprint $table) {
            $table->longText('analysis')->nullable()->after('consent_ip');

            // pending — разбор в очереди; ready — закончен; failed — не удался,
            // и собеседование ждёт человека. Отдельно от stage: стадия говорит,
            // где кандидат, а это — что с разбором.
            $table->string('analysis_status', 12)->default('none')->after('analysis');
            $table->timestamp('analysed_at')->nullable()->after('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interviews', function (Blueprint $table) {
            $table->dropColumn(['analysis', 'analysis_status', 'analysed_at']);
        });
    }
};
