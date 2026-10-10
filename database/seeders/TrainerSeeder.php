<?php

namespace Database\Seeders;

use App\Models\Trainer\TrainerPackage;
use App\Models\Trainer\TrainerTrack;
use Illuminate\Database\Seeder;

/**
 * Тренажёр: направления и пакеты попыток по умолчанию.
 *
 * Повторный запуск ничего не дублирует и не трогает то, что админ уже
 * поправил руками: запись ищется по названию и создаётся, только если её нет.
 */
class TrainerSeeder extends Seeder
{
    public const TRACKS = [
        [
            'title' => 'PHP-разработчик',
            'description' => 'Бэкенд на PHP и Laravel: архитектура, базы данных, API.',
            'skills' => [
                ['label' => 'PHP и Laravel', 'kind' => 'must', 'weight' => 40],
                ['label' => 'SQL и базы данных', 'kind' => 'must', 'weight' => 30],
                ['label' => 'REST API', 'kind' => 'nice', 'weight' => 20],
                ['label' => 'Git', 'kind' => 'nice', 'weight' => 10],
            ],
        ],
        [
            'title' => 'Frontend-разработчик',
            'description' => 'Вёрстка и JavaScript: интерфейсы, работа с API, производительность.',
            'skills' => [
                ['label' => 'JavaScript', 'kind' => 'must', 'weight' => 40],
                ['label' => 'HTML и CSS', 'kind' => 'must', 'weight' => 30],
                ['label' => 'React или Vue', 'kind' => 'nice', 'weight' => 20],
                ['label' => 'Git', 'kind' => 'nice', 'weight' => 10],
            ],
        ],
        [
            'title' => 'Бухгалтер',
            'description' => 'Учёт и отчётность по законодательству Таджикистана.',
            'skills' => [
                ['label' => 'Бухгалтерский учёт и проводки', 'kind' => 'must', 'weight' => 40],
                ['label' => 'Налоговый учёт', 'kind' => 'must', 'weight' => 30],
                ['label' => 'Excel', 'kind' => 'nice', 'weight' => 20],
                ['label' => '1С', 'kind' => 'nice', 'weight' => 10],
            ],
        ],
        [
            'title' => 'Менеджер по продажам',
            'description' => 'Работа с клиентами: поиск, переговоры, возражения, CRM.',
            'skills' => [
                ['label' => 'Переговоры и работа с возражениями', 'kind' => 'must', 'weight' => 40],
                ['label' => 'Поиск клиентов', 'kind' => 'must', 'weight' => 30],
                ['label' => 'CRM', 'kind' => 'nice', 'weight' => 20],
                ['label' => 'Расчёты и скидки в Excel', 'kind' => 'nice', 'weight' => 10],
            ],
        ],
    ];

    public const PACKAGES = [
        ['title' => '3 тренировки', 'attempts' => 3, 'price' => 30],
        ['title' => '10 тренировок', 'attempts' => 10, 'price' => 80],
        ['title' => '30 тренировок', 'attempts' => 30, 'price' => 200],
    ];

    public function run(): void
    {
        foreach (self::TRACKS as $i => $track) {
            TrainerTrack::firstOrCreate(['title' => $track['title']], $track + [
                'language' => 'ru',
                'active' => true,
                'position' => $i,
            ]);
        }

        foreach (self::PACKAGES as $i => $package) {
            TrainerPackage::firstOrCreate(['title' => $package['title']], $package + [
                'active' => true,
                'position' => $i,
            ]);
        }
    }
}
