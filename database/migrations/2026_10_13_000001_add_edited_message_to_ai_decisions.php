<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Письмо кандидату, исправленное работодателем перед отправкой.
 *
 * ИИ пишет черновик, но последнее слово — за работодателем, в том числе в
 * формулировках. Исправленный текст хранится отдельно: что предложил ИИ,
 * остаётся в message_to_candidate, и в отчёте видно и то, и другое.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_decisions', function (Blueprint $table) {
            $table->text('edited_message')->nullable()->after('override_message');
        });
    }

    public function down(): void
    {
        Schema::table('ai_decisions', function (Blueprint $table) {
            $table->dropColumn('edited_message');
        });
    }
};
