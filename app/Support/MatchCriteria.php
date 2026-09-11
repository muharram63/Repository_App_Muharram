<?php

namespace App\Support;

use App\Models\Resume;
use App\Models\Vacancy;

/**
 * Критерии совпадения кандидата и вакансии, которые считаются точно.
 *
 * Опыт, зарплата и город — это числа и названия, а не смысл текста: спрашивать
 * о них модель значило бы платить за то, что известно наверняка, и рисковать
 * ошибкой там, где её быть не может. Модели остаются навыки.
 */
class MatchCriteria
{
    /** Сколько лет опыта стоит за каждым значением требования вакансии. */
    private const EXPERIENCE = [
        'not' => 0,
        'year' => 1,
        '3_years' => 3,
        '3-6years' => 3,
        'more_6years' => 6,
    ];

    /**
     * Вес критерия в общей оценке. Навыки весомее всего: остальное — условия,
     * о которых можно договориться, а навыков либо нет, либо есть.
     */
    public const WEIGHTS = [
        'role' => 20,
        'skills' => 45,
        'experience' => 20,
        'salary' => 8,
        'city' => 7,
    ];

    /**
     * Критерий из ответа модели: направление и навыки сверить строками нельзя.
     * «Backend-разработчик» и «PHP-программист» — одно и то же, а «Оператор
     * станка» и «Оператор call-центра» — разное, хотя написано похоже.
     */
    public static function fromModel(string $key, string $label, ?array $answer): array
    {
        $state = (string) ($answer['state'] ?? 'unknown');

        return self::row(
            $key,
            $label,
            in_array($state, ['ok', 'partial', 'no'], true) ? $state : 'unknown',
            (string) ($answer['note'] ?? ''),
        );
    }

    /**
     * Точные критерии пары. Критерий без данных получает состояние unknown
     * и в оценку не входит — иначе незаполненное поле занижало бы результат.
     *
     * @return array<int,array{key:string,label:string,state:string,note:string}>
     */
    public static function for(Vacancy $vacancy, Resume $resume): array
    {
        return array_values(array_filter([
            self::experience($vacancy, $resume),
            self::salary($vacancy, $resume),
            self::city($vacancy, $resume),
        ]));
    }

    private static function experience(Vacancy $vacancy, Resume $resume): array
    {
        $needed = self::EXPERIENCE[$vacancy->experience_required] ?? null;
        $has = (int) $resume->experience_years;

        if ($needed === null) {
            return self::row('experience', 'Опыт', 'unknown', 'Требование не указано');
        }

        if ($needed === 0) {
            return self::row('experience', 'Опыт', 'ok', 'Опыт не требуется');
        }

        $note = $has.' '.self::years($has).' при требуемых от '.$needed;

        return self::row('experience', 'Опыт', match (true) {
            $has >= $needed => 'ok',
            // год недобора — повод поговорить, а не отказать
            $has >= $needed - 1 => 'partial',
            default => 'no',
        }, $note);
    }

    private static function salary(Vacancy $vacancy, Resume $resume): array
    {
        $wants = (int) $resume->desired_salary;
        $offers = (int) $vacancy->salary_to;

        if ($wants <= 0 || $offers <= 0) {
            return self::row('salary', 'Зарплата', 'unknown', 'Не указана с одной из сторон');
        }

        $note = 'ожидает '.number_format($wants, 0, ',', ' ')
            .', вакансия до '.number_format($offers, 0, ',', ' ');

        return self::row('salary', 'Зарплата', match (true) {
            $wants <= $offers => 'ok',
            // небольшой разрыв обычно закрывается на переговорах
            $wants <= $offers * 1.15 => 'partial',
            default => 'no',
        }, $note);
    }

    private static function city(Vacancy $vacancy, Resume $resume): array
    {
        if ($vacancy->work_schedule === 'remote_work') {
            return self::row('city', 'Город', 'ok', 'Удалённая работа, город не важен');
        }

        $where = trim((string) $vacancy->city?->region);
        $lives = trim((string) $resume->applicant?->city);

        if ($where === '' || $lives === '') {
            return self::row('city', 'Город', 'unknown', 'Город не указан');
        }

        $same = mb_strtolower($where) === mb_strtolower($lives)
            || str_contains(mb_strtolower($where), mb_strtolower($lives))
            || str_contains(mb_strtolower($lives), mb_strtolower($where));

        return self::row(
            'city',
            'Город',
            $same ? 'ok' : 'no',
            $same ? $lives : $lives.', вакансия в городе '.$where,
        );
    }

    /**
     * Общая оценка: взвешенное среднее по критериям, о которых есть данные.
     *
     * Критерии со state = unknown исключаются, а веса оставшихся
     * пересчитываются — незаполненное поле не должно портить результат.
     */
    public static function score(array $criteria): ?int
    {
        $sum = 0;
        $weight = 0;

        foreach ($criteria as $one) {
            $share = self::WEIGHTS[$one['key']] ?? 0;

            if ($one['state'] === 'unknown' || $share === 0) {
                continue;
            }

            $weight += $share;
            $sum += $share * match ($one['state']) {
                'ok' => 1,
                'partial' => 0.5,
                default => 0,
            };
        }

        return $weight > 0 ? (int) round($sum / $weight * 100) : null;
    }

    public static function row(string $key, string $label, string $state, string $note): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $state, 'note' => $note];
    }

    private static function years(int $count): string
    {
        $last = $count % 10;
        $tens = $count % 100;

        return match (true) {
            $tens >= 11 && $tens <= 14 => 'лет',
            $last === 1 => 'год',
            $last >= 2 && $last <= 4 => 'года',
            default => 'лет',
        };
    }
}
