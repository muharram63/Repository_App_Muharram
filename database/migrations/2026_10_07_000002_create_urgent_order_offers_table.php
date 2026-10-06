<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Отклики мастеров на срочные заявки.
 *
 * Мастер — соискатель с портфолио: заказчик выбирает по фото сделанных работ,
 * поэтому отклик привязан к applicants, а не к users. Один отклик на заявку
 * от мастера: отозванный можно вернуть — та же строка снова становится
 * pending, а не плодит дубли.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urgent_order_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urgent_order_id')->constrained('urgent_orders')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->text('message');
            // цена мастера в сомони, по желанию — «назову после осмотра»
            $table->unsignedInteger('price')->nullable();
            // когда сможет приступить: today | tomorrow | later
            $table->string('can_start', 10);
            // pending | chosen | declined | withdrawn
            $table->string('status', 10)->default('pending');
            $table->timestamps();

            $table->unique(['urgent_order_id', 'applicant_id']);
            $table->index(['applicant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urgent_order_offers');
    }
};
