<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Дубли в справочнике городов.
 *
 * Один и тот же город был записан дважды — по-разному: «Кулоб» и
 * «Хатлонская область, город Куляб», «Исфара» и «Согдийская область, город
 * Исфара», «Конибодом» и «Канибадам», а Душанбе ещё и с ошибкой — «Согдийская
 * область, город Душанбе». В формах это выглядело как разные города, и
 * компании с вакансиями расходились по двум записям.
 *
 * Правильная запись — та, которой пользуются сиды проекта (TajikDataSeeder).
 * Всё, что ссылалось на дубль (компании, вакансии, срочные заявки), переходит
 * на неё, дубль удаляется. Записи ищутся по названию, а не по id: на другом
 * сервере номера могут быть другими, а какой-то пары может не быть вовсе.
 */
return new class extends Migration
{
    /** дубль => правильная запись */
    private const MERGE = [
        'Согдийская область, город Душанбе' => 'город Душанбе',
        'Согдийская область, город Конибодом' => 'Согдийская область, город Канибадам',
        'Кулоб' => 'Хатлонская область, город Куляб',
        'Исфара' => 'Согдийская область, город Исфара',
    ];

    /** таблицы со ссылкой на город */
    private const REFERENCES = ['employers', 'vacancies', 'urgent_orders'];

    public function up(): void
    {
        foreach (self::MERGE as $duplicate => $canonical) {
            $from = DB::table('cities')->where('region', $duplicate)->value('id');

            if (! $from) {
                continue;
            }

            $to = DB::table('cities')->where('region', $canonical)->value('id');

            // правильной записи нет — дубль и есть единственная: просто исправляем название
            if (! $to) {
                DB::table('cities')->where('id', $from)->update(['region' => $canonical, 'updated_at' => now()]);

                continue;
            }

            DB::transaction(function () use ($from, $to) {
                foreach (self::REFERENCES as $table) {
                    DB::table($table)->where('city_id', $from)->update(['city_id' => $to]);
                }

                DB::table('cities')->where('id', $from)->delete();
            });
        }
    }

    /** Объединение не откатывается: какие записи были на дубле, уже не узнать. */
    public function down(): void
    {
    }
};
