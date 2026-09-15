<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Industry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Убирает служебные заглушки из справочников категорий и отраслей.
 *
 * В базе остались записи «category 7» и «industry 1»…«industry 7» — следы
 * ранних проб. Они видны пользователям в фильтрах каталога и в карточках
 * компаний, но ничего не значат.
 *
 * Просто удалить их нельзя: на заглушки-категории завязаны настоящие отрасли,
 * а на заглушки-отрасли — несколько компаний. Поэтому сперва переносим всё
 * на правильные записи и только потом удаляем.
 *
 *   php artisan refs:cleanup --dry-run
 *   php artisan refs:cleanup
 */
class CleanupReferenceData extends Command
{
    protected $signature = 'refs:cleanup {--dry-run : Только показать, ничего не меняя}';

    protected $description = 'Удалить заглушки из справочников категорий и отраслей';

    /** Какой категории на самом деле принадлежит отрасль. */
    private const INDUSTRY_CATEGORY = [
        'Программное обеспечение' => 'IT и разработка',
        'Розничная торговля' => 'Продажи',
        'Реклама и PR' => 'Маркетинг',
        'Банки и финансы' => 'Финансы',
        'Перевозки и склад' => 'Логистика',
        'Строительство и ремонт' => 'Строительство',
        'Здравоохранение' => 'Медицина',
        'Обучение и курсы' => 'Образование и наука',
        'Промышленность' => 'Производство',
        'Общественное питание' => 'Сфера услуг',
    ];

    /** Куда переносим компании, стоявшие на заглушках. */
    private const FALLBACK_CATEGORY = 'Продажи';

    private const FALLBACK_INDUSTRY = 'Розничная торговля';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $junkCategories = Category::whereRaw("name REGEXP '^category[[:space:]]*[0-9]+$'")->pluck('id', 'name');
        $junkIndustries = Industry::whereRaw("name REGEXP '^industry[[:space:]]*[0-9]+$'")->pluck('id', 'name');

        if ($junkCategories->isEmpty() && $junkIndustries->isEmpty()) {
            $this->info('Заглушек в справочниках нет — всё чисто.');

            return self::SUCCESS;
        }

        $orphanIndustries = Industry::whereIn('category_id', $junkCategories->values())
            ->whereNotIn('id', $junkIndustries->values())
            ->count();

        $strandedEmployers = DB::table('employers')
            ->where(function ($q) use ($junkCategories, $junkIndustries) {
                $q->whereIn('category_id', $junkCategories->values())
                    ->orWhereIn('industry_id', $junkIndustries->values());
            })
            ->count();

        $this->table(['Что делаем', 'Сколько'], [
            ['удалим категорий-заглушек', $junkCategories->count()],
            ['удалим отраслей-заглушек', $junkIndustries->count()],
            ['перенесём настоящих отраслей', $orphanIndustries],
            ['перенесём компаний', $strandedEmployers],
        ]);

        if ($dry) {
            $this->comment('Пробный запуск: ничего не изменено.');

            return self::SUCCESS;
        }

        $fallbackCategory = Category::where('name', self::FALLBACK_CATEGORY)->value('id');
        $fallbackIndustry = Industry::where('name', self::FALLBACK_INDUSTRY)->value('id');

        if (! $fallbackCategory || ! $fallbackIndustry) {
            $this->error('Нет запасных записей справочника — сначала загрузите справочники.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($junkCategories, $junkIndustries, $fallbackCategory, $fallbackIndustry) {
            // 1) настоящие отрасли возвращаем в свои категории
            foreach (self::INDUSTRY_CATEGORY as $industry => $category) {
                $categoryId = Category::where('name', $category)->value('id');

                if ($categoryId) {
                    Industry::where('name', $industry)->update(['category_id' => $categoryId]);
                }
            }

            // 2) компании со заглушек переводим на запасные записи
            DB::table('employers')->whereIn('category_id', $junkCategories->values())
                ->update(['category_id' => $fallbackCategory]);
            DB::table('employers')->whereIn('industry_id', $junkIndustries->values())
                ->update(['industry_id' => $fallbackIndustry]);

            // 3) и только теперь удаляем сами заглушки
            Industry::whereIn('id', $junkIndustries->values())->delete();
            Category::whereIn('id', $junkCategories->values())->delete();
        });

        $this->info('Готово: справочники содержат только осмысленные записи.');

        return self::SUCCESS;
    }
}
