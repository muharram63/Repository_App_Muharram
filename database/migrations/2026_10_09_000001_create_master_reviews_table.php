<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Отзывы и оценки мастерам.
 *
 * Отзыв оставляет только заказчик и только по выполненной заявке, где этот
 * мастер был исполнителем: оценка привязана к реальной работе, накрутить её
 * без заказов нельзя. Один отзыв на заявку — уникальный ключ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urgent_order_id')->unique()->constrained('urgent_orders')->cascadeOnDelete();
            // мастер, которого оценивают
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            // заказчик, который оценивает
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['applicant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_reviews');
    }
};
