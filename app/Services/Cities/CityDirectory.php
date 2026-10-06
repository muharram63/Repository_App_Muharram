<?php

namespace App\Services\Cities;

use App\Models\City;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Справочник городов: поиск дублей и объединение.
 *
 * Один город пишут по-разному: с областью и без («Исфара» и «Согдийская
 * область, город Исфара»), по-русски и по-таджикски («Куляб» и «Кулоб»,
 * «Канибадам» и «Конибодом»). Сравниваем по «ключу» — названию самого города
 * в нижнем регистре, с таджикскими написаниями, сведёнными к русским.
 */
class CityDirectory
{
    /** таблицы со ссылкой на город — при объединении переносятся все */
    public const REFERENCES = ['employers', 'vacancies', 'urgent_orders'];

    /** таджикское или старое написание => русское, как в справочнике */
    private const ALIASES = [
        'кулоб' => 'куляб',
        'кӯлоб' => 'куляб',
        'конибодом' => 'канибадам',
        'хуҷанд' => 'худжанд',
        'хучанд' => 'худжанд',
        'ҳисор' => 'гиссар',
        'хисор' => 'гиссар',
        'турсунзода' => 'турсунзаде',
        'панҷакент' => 'пенджикент',
        'панчакент' => 'пенджикент',
        'норак' => 'нурек',
        'хоруғ' => 'хорог',
        'хоруг' => 'хорог',
        'шахристон' => 'шахристан',
    ];

    /**
     * Ключ города: «Согдийская область, город Исфара» → «исфара»,
     * «г. Кулоб» → «куляб».
     */
    public static function key(?string $region): string
    {
        $name = trim((string) $region);

        if (preg_match('/(?:город|г\.)\s*(.+)$/u', $name, $m)) {
            $name = $m[1];
        } elseif (str_contains($name, ',')) {
            $name = Str::afterLast($name, ',');
        }

        $name = Str::lower(trim($name, " \t.,"));

        return self::ALIASES[$name] ?? $name;
    }

    /**
     * Город с тем же ключом, кроме указанного, — уже есть в справочнике.
     */
    public function existing(string $region, ?int $exceptId = null): ?City
    {
        $key = self::key($region);

        return City::query()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get()
            ->first(fn (City $city) => self::key($city->region) === $key);
    }

    /**
     * Группы дублей: id города => другие записи того же города.
     *
     * @return Collection<int, Collection<int, City>>
     */
    public function duplicates(): Collection
    {
        $cities = City::orderBy('id')->get();

        $result = collect();

        // flatMap здесь не годится: он перенумеровывает ключи, а ключ — id города
        $cities->groupBy(fn (City $city) => self::key($city->region))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use ($result) {
                foreach ($group as $city) {
                    $result->put($city->id, $group->reject(fn (City $other) => $other->is($city))->values());
                }
            });

        return $result;
    }

    /**
     * То же для многих городов сразу — по запросу на таблицу, а не на город:
     * список в админке иначе делал бы три запроса на каждую строку.
     *
     * @param  iterable<City>  $cities
     * @return array<int, array<string,int>>  id города => таблица => сколько
     */
    public function usageMany(iterable $cities): array
    {
        $ids = collect($cities)->pluck('id')->all();
        $counts = [];

        foreach (self::REFERENCES as $table) {
            $counts[$table] = DB::table($table)->whereIn('city_id', $ids)
                ->selectRaw('city_id, count(*) as total')->groupBy('city_id')
                ->pluck('total', 'city_id');
        }

        return collect($ids)->mapWithKeys(fn (int $id) => [$id => collect(self::REFERENCES)
            ->mapWithKeys(fn (string $table) => [$table => (int) ($counts[$table][$id] ?? 0)])
            ->all()])->all();
    }

    /** Сколько записей ссылается на город — по таблицам. */
    public function usage(City $city): array
    {
        return collect(self::REFERENCES)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->where('city_id', $city->id)->count()])
            ->all();
    }

    /**
     * Объединить дубль с правильной записью: всё, что ссылалось на $from,
     * переходит на $to, а $from удаляется.
     *
     * @return array<string,int> сколько записей перенесено, по таблицам
     */
    public function merge(City $from, City $to): array
    {
        if ($from->is($to)) {
            throw new \InvalidArgumentException('Нельзя объединить город с самим собой.');
        }

        return DB::transaction(function () use ($from, $to) {
            $moved = [];

            foreach (self::REFERENCES as $table) {
                $moved[$table] = DB::table($table)->where('city_id', $from->id)->update(['city_id' => $to->id]);
            }

            $from->delete();

            return $moved;
        });
    }
}
