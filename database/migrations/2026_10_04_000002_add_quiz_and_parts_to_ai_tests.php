<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Тестовая часть из нескольких заданий.
 *
 * Было одно практическое задание. Теперь — тест с вариантами ответа по
 * профессии (проверяется кодом, мгновенно) и несколько практических заданий
 * (проверяет модель, каждое отдельно). Первое практическое задание остаётся в
 * прежних колонках: старые задания и отчёты по ним продолжают работать.
 *
 * Всё шифруется, как и прежнее содержимое: в тесте — верные ответы, в
 * ответах кандидата — его труд.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_test_tasks', function (Blueprint $table) {
            // [{question, options[], answer, explain}] — верные ответы кандидату не показываются
            $table->longText('quiz')->nullable()->after('rubric');
            // дополнительные практические задания: [{statement, format, language, reference_solution, rubric}]
            $table->longText('parts')->nullable()->after('quiz');
        });

        Schema::table('ai_test_submissions', function (Blueprint $table) {
            // {quiz: [номер варианта|null], parts: [решение доп. заданий]}
            $table->longText('answers')->nullable()->after('content');
            // разбор по частям: {quiz: {...}, parts: [{score, summary, review, execution}]}
            $table->longText('details')->nullable()->after('review');
        });
    }

    public function down(): void
    {
        Schema::table('ai_test_tasks', function (Blueprint $table) {
            $table->dropColumn(['quiz', 'parts']);
        });

        Schema::table('ai_test_submissions', function (Blueprint $table) {
            $table->dropColumn(['answers', 'details']);
        });
    }
};
