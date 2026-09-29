<?php

namespace App\Console\Commands;

use App\Services\Ai\AiUnavailableException;
use App\Services\Scoring\CalibrationProfiles;
use App\Services\Scoring\CalibrationRunner;
use App\Services\Scoring\DecisionEngine;
use Illuminate\Console\Command;
use Throwable;

/**
 * Калибровка ИИ-отбора на десяти заготовленных кандидатах.
 *
 * Пороги 45 и 70 и веса 30/40/30 — числа, которые кто-то однажды поставил.
 * Команда прогоняет через отбор заведомо разных людей и показывает, что из
 * этого вышло: сильные должны проходить, слабые — получать отказ, пограничные —
 * уходить к человеку. Если разделения нет, числа надо менять, и видно это
 * только так.
 *
 *   php artisan ai:calibrate                 прогнать всех десятерых
 *   php artisan ai:calibrate --only=pushy    прогнать одного
 *   php artisan ai:calibrate --keep          оставить данные в базе
 *   php artisan ai:calibrate --purge         убрать оставленное, ничего не считая
 *   php artisan ai:calibrate --json=out.json сохранить отчёт файлом
 *
 * Прогон стоит квоты: на каждого кандидата уходит полтора-два десятка
 * обращений к модели. Поэтому команда спрашивает подтверждение и после себя
 * убирает — служебные кандидаты не должны осесть в списке у работодателя.
 */
class CalibrateAiScreening extends Command
{
    protected $signature = 'ai:calibrate
        {--only=* : Прогнать только эти профили (slug)}
        {--keep : Не удалять созданные данные после прогона}
        {--purge : Только убрать данные прошлого прогона и выйти}
        {--json= : Сохранить отчёт в файл}';

    protected $description = 'Прогнать калибровочных кандидатов через ИИ-отбор и показать, разделяют ли их пороги';

    public function handle(CalibrationRunner $runner): int
    {
        if ($this->option('purge')) {
            $removed = $runner->cleanup();

            $this->info('Убрано: пользователей — '.$removed['users']
                .', вакансий — '.$removed['vacancies'].'.');

            return self::SUCCESS;
        }

        $profiles = $this->chosen();

        if ($profiles === []) {
            $this->error('Таких профилей нет. Доступные: '.implode(', ', CalibrationProfiles::slugs()));

            return self::FAILURE;
        }

        $this->intro($profiles);

        if (! $this->confirm('Запустить прогон?', true)) {
            return self::SUCCESS;
        }

        // Остатки прошлого прогона убираем до, а не только после: иначе
        // прерванный Ctrl+C прогон оставляет мусор, который копится.
        $runner->cleanup();

        try {
            $vacancy = $runner->vacancy();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($profiles as $profile) {
            $this->line('→ '.$profile['name']);

            try {
                $rows[] = $runner->run($profile, $vacancy);
            } catch (AiUnavailableException $e) {
                /*
                 * Кончилась квота или модель недоступна. Останавливаемся, но
                 * показываем то, что уже посчитано: половина калибровки лучше,
                 * чем ничего, и повторять её бесплатно нельзя.
                 */
                $this->warn('Модель недоступна: '.$e->getMessage());
                $this->warn('Прогон остановлен, ниже — то, что успели посчитать.');
                break;
            } catch (Throwable $e) {
                $this->warn('Профиль «'.$profile['slug'].'» не прошёл: '.$e->getMessage());
            }
        }

        $this->report($rows);

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line('Отчёт сохранён: '.$path);
        }

        if ($this->option('keep')) {
            $this->warn('Данные оставлены в базе: '.$vacancy->title.' (вакансия #'.$vacancy->id.').');
            $this->warn('Убрать их потом: php artisan ai:calibrate --purge');
        } else {
            $removed = $runner->cleanup();
            $this->line('Убрано: пользователей — '.$removed['users'].', вакансий — '.$removed['vacancies'].'.');
        }

        return collect($rows)->every(fn (array $row) => $row['matched'])
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Профили, которые просили прогнать.
     *
     * @return array<int,array>
     */
    private function chosen(): array
    {
        $only = (array) $this->option('only');

        if ($only === []) {
            return CalibrationProfiles::all();
        }

        return array_values(array_filter(
            array_map(fn (string $slug) => CalibrationProfiles::find($slug), $only)
        ));
    }

    private function intro(array $profiles): void
    {
        $vacancy = CalibrationProfiles::VACANCY;

        $this->info('Калибровка ИИ-отбора');
        $this->line('Вакансия: '.$vacancy['title'].', уровень '.$vacancy['level']
            .', вопросов '.$vacancy['questions_count']);
        $this->line('Веса: документы '.$vacancy['weight_documents']
            .' / собеседование '.$vacancy['weight_interview']
            .' / задание '.$vacancy['weight_test']);
        $this->line('Пороги: отказ ниже '.$vacancy['threshold_reject']
            .', приём от '.$vacancy['threshold_accept']);
        $this->line('Кандидатов: '.count($profiles)
            .'. Каждый — полтора-два десятка обращений к модели.');
        $this->newLine();
    }

    /**
     * Итоги прогона.
     *
     * @param  array<int,array>  $rows
     */
    private function report(array $rows): void
    {
        if ($rows === []) {
            $this->warn('Считать нечего.');

            return;
        }

        $this->newLine();
        $this->table(
            ['Кандидат', 'Ждали', 'Вышло', 'Док', 'Соб', 'Зад', 'Итог', 'Осн.', '?'],
            array_map(fn (array $r) => [
                $r['slug'],
                CalibrationProfiles::BUCKETS[$r['expect']] ?? $r['expect'],
                $r['outcome'] ? (CalibrationProfiles::BUCKETS[$r['outcome']] ?? $r['outcome']) : '—',
                $r['documents'] ?? '—',
                $r['interview'] ?? '—',
                $r['test'] ?? '—',
                $r['total'] ?? '—',
                $r['gate'] ?? '—',
                $r['matched'] ? 'да' : 'НЕТ',
            ], $rows),
        );

        $matched = count(array_filter($rows, fn (array $r) => $r['matched']));

        $this->line('Совпало с ожиданием: '.$matched.' из '.count($rows).'.');

        $this->separation($rows);

        foreach ($rows as $row) {
            if ($row['note']) {
                $this->warn($row['slug'].': '.$row['note']);
            }
        }
    }

    /**
     * Главное число калибровки: зазор между теми, кого ждали в приём, и теми,
     * кого ждали в отказ.
     *
     * Совпавшие исходы ещё ничего не доказывают: они могли сойтись впритык, и
     * тогда любой следующий кандидат ляжет не туда. Смысл имеет расстояние
     * между группами и то, попадают ли пороги внутрь него.
     *
     * @param  array<int,array>  $rows
     */
    private function separation(array $rows): void
    {
        $scores = fn (string $expect) => collect($rows)
            ->where('expect', $expect)
            ->pluck('total')
            ->filter(fn ($t) => $t !== null);

        $passed = $scores(DecisionEngine::PASSED);
        $rejected = $scores(DecisionEngine::REJECTED);

        if ($passed->isEmpty() || $rejected->isEmpty()) {
            return;
        }

        $floor = $passed->min();
        $ceiling = $rejected->max();

        $this->line('Слабейший из сильных: '.$floor.'. Сильнейший из слабых: '.$ceiling.'.');

        if ($ceiling >= $floor) {
            $this->error('Группы перекрываются — порогом их не разделить, числа надо пересматривать.');

            return;
        }

        $this->info('Зазор между группами: '.($floor - $ceiling).' баллов.');

        $reject = CalibrationProfiles::VACANCY['threshold_reject'];
        $accept = CalibrationProfiles::VACANCY['threshold_accept'];

        if ($reject > $ceiling && $accept <= $floor) {
            $this->info('Пороги ('.$reject.'/'.$accept.') лежат внутри зазора — стоят верно.');

            return;
        }

        $this->warn('Пороги ('.$reject.'/'.$accept.') в зазор не попадают: '
            .'разумнее отказ выше '.$ceiling.' и приём не выше '.$floor.'.');
    }
}
