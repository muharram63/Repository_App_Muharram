<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Срочные заявки: «сломалась стиральная машина, нужен мастер сегодня».
 *
 * Создаёт любой вошедший пользователь — у частного заказчика нет компании,
 * поэтому заявка привязана к users, а не к employers.
 *
 * Адрес — закрытое поле: до выбора исполнителя мастера видят только город и
 * район, точный адрес открывается выбранному (этап 6). Фото проблемы лежат
 * на диске public в orders/{id}/ и, как фото портфолио, пересохраняются без
 * EXIF — с GPS-координатами квартиры заказчика.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urgent_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('work_category_id')->nullable()->constrained('work_categories')->nullOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('district', 120)->nullable();
            // видит только заказчик, администратор и выбранный мастер
            $table->string('address', 255)->nullable();
            // today | 1d | 3d — что выбрал заказчик; deadline_at — когда это истекает
            $table->string('deadline_option', 8);
            $table->timestamp('deadline_at');
            // примерный бюджет в сомони, по желанию
            $table->unsignedInteger('budget')->nullable();
            // open | assigned | done | expired | cancelled
            $table->string('status', 12)->default('open');
            // выбранный отклик мастера — появится на этапе 6
            $table->unsignedBigInteger('chosen_offer_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // лента: открытые по сроку; просрочка: открытые с истёкшим сроком
            $table->index(['status', 'deadline_at']);
            $table->index(['work_category_id', 'status']);
        });

        Schema::create('urgent_order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urgent_order_id')->constrained('urgent_orders')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urgent_order_photos');
        Schema::dropIfExists('urgent_orders');
    }
};
