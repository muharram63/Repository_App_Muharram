<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Категории работ мастеров: сантехника, электрика, покраска…
 *
 * Отдельно от categories: те описывают отрасли компаний и вакансий
 * («IT и разработка», «Финансы»), а здесь — виды работ, по которым мастер
 * показывает портфолио и откликается на срочные заявки. Смешай их — и в
 * фильтре вакансий появилась бы «Сантехника», а в заявке — «Маркетинг».
 *
 * name — русское название и одновременно ключ перевода (lang/*.json), как у
 * городов. Список вставляется здесь же, чтобы категории были в любой базе
 * сразу после миграции, без отдельного сидера.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        ['plumbing', 'Сантехника', '🚰'],
        ['electrical', 'Электрика', '💡'],
        ['construction', 'Строительство и отделка', '🧱'],
        ['painting', 'Покраска', '🎨'],
        ['appliances', 'Ремонт бытовой техники', '🧺'],
        ['auto', 'Ремонт автомобилей', '🚗'],
        ['art', 'Художественные работы', '🖌️'],
        ['other', 'Другое', '🛠️'],
    ];

    public function up(): void
    {
        Schema::create('work_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 120);
            $table->string('icon', 16)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('work_categories')->insert(array_map(
            fn (array $row, int $position) => [
                'slug' => $row[0],
                'name' => $row[1],
                'icon' => $row[2],
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::CATEGORIES,
            array_keys(self::CATEGORIES),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('work_categories');
    }
};
