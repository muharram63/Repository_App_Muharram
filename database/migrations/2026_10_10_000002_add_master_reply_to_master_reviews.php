<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Ответ мастера на отзыв — публичный, под самим отзывом.
 *
 * Несправедливый отзыв («опоздал», а заказчик сам перенёс время) без ответа
 * висит у мастера в профиле навсегда. Ответ — его способ объяснить, а
 * будущим заказчикам — увидеть обе стороны.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_reviews', function (Blueprint $table) {
            $table->text('master_reply')->nullable()->after('comment');
            $table->timestamp('replied_at')->nullable()->after('master_reply');
        });
    }

    public function down(): void
    {
        Schema::table('master_reviews', function (Blueprint $table) {
            $table->dropColumn(['master_reply', 'replied_at']);
        });
    }
};
