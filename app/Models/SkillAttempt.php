<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Попытка кандидата пройти задание по навыку.
 *
 * Результат лучшей завершённой попытки и есть тот самый «подтверждённый
 * навык», который видит работодатель вместо строчки в резюме.
 */
class SkillAttempt extends Model
{
    /** С какого результата навык считается подтверждённым. */
    public const PASSING = 60;

    /** Сколько даётся на задание: столько же, сколько обещано кандидату. */
    public const MINUTES = 15;

    /**
     * Запас на отправку. Ответ, ушедший за секунду до конца, может дойти уже
     * после — наказывать за скорость сети было бы нечестно.
     */
    public const GRACE_SECONDS = 45;

    /**
     * Пауза перед следующей попыткой по тому же навыку. Без неё задание можно
     * было бы перебирать подряд, пока не запомнятся ответы.
     */
    public const COOLDOWN_MINUTES = 60;

    protected $fillable = [
        'applicant_id', 'skill_test_id', 'answers', 'review', 'score', 'expires_at', 'finished_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'review' => 'array',
        'expires_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Время вышло — с запасом на доставку ответа.
     */
    public function expired(): bool
    {
        return $this->expires_at !== null
            && now()->greaterThan($this->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /**
     * Сколько секунд осталось: по этому числу идёт отсчёт на странице.
     */
    public function secondsLeft(): int
    {
        if ($this->finished_at || $this->expires_at === null) {
            return 0;
        }

        // diffInSeconds возвращает дробное число, а метод обещает целое:
        // без явного округления PHP 8.1+ ругается на потерю точности
        return (int) max(0, floor(now()->diffInSeconds($this->expires_at, false)));
    }

    /**
     * Закрыть попытку, на которую не хватило времени.
     */
    public function closeExpired(): void
    {
        $this->update([
            'review' => [],
            'score' => 0,
            'finished_at' => $this->expires_at ?? now(),
        ]);
    }

    /**
     * Когда соискателю снова можно взяться за этот навык.
     */
    public static function cooldownUntil(int $applicantId, int $skillId): ?\Illuminate\Support\Carbon
    {
        $last = static::where('applicant_id', $applicantId)
            ->whereNotNull('finished_at')
            ->whereHas('test', fn ($t) => $t->where('skill_id', $skillId))
            ->latest('finished_at')
            ->first();

        if (! $last) {
            return null;
        }

        $until = $last->finished_at->copy()->addMinutes(self::COOLDOWN_MINUTES);

        return $until->isFuture() ? $until : null;
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function test()
    {
        return $this->belongsTo(SkillTest::class, 'skill_test_id');
    }

    public function passed(): bool
    {
        return $this->score !== null && $this->score >= self::PASSING;
    }

    /**
     * Вес уровня для сравнения попыток: продвинутый весомее начального.
     */
    public function levelWeight(): int
    {
        $position = array_search($this->test?->level, array_keys(Skill::LEVELS), true);

        return $position === false ? 0 : $position;
    }

    /**
     * Подтверждённые навыки соискателя: по лучшей завершённой попытке.
     *
     * Проваленная попытка навык не подтверждает, но и не наказывает —
     * человек может пройти задание снова, и это часть замысла.
     *
     * @return \Illuminate\Support\Collection<int,array{skill:string,level:string,score:int,at:mixed}>
     */
    public static function badgesFor(?Applicant $applicant)
    {
        if (! $applicant) {
            return collect();
        }

        // в каталоге попытки уже загружены заранее — второй запрос на каждую
        // карточку превратил бы страницу в два десятка лишних обращений к базе
        $attempts = $applicant->relationLoaded('skillAttempts')
            ? $applicant->skillAttempts
            : static::where('applicant_id', $applicant->id)
                ->whereNotNull('finished_at')
                ->where('score', '>=', self::PASSING)
                ->with('test.skill')
                ->get();

        return static::summarise($attempts);
    }

    /**
     * Лучший результат по каждому навыку из набора попыток.
     */
    public static function summarise($attempts)
    {
        return collect($attempts)
            ->filter(fn (self $a) => $a->finished_at && $a->passed())
            ->groupBy(fn (self $a) => $a->test?->skill_id)
            // Из нескольких попыток по навыку показываем сильнейшую: сначала
            // по уровню, потом по баллу. Продвинутый на 70% говорит о человеке
            // больше, чем начальный на 100%, и работодателю важнее именно это.
            ->map(fn ($group) => $group
                ->sortByDesc(fn (self $a) => [$a->levelWeight(), (int) $a->score])
                ->first())
            ->filter(fn (?self $a) => $a && $a->test?->skill)
            ->map(fn (self $a) => [
                'skill' => $a->test->skill->name,
                'level' => $a->test->levelLabel(),
                'score' => (int) $a->score,
                'at' => $a->finished_at,
            ])
            ->sortByDesc('score')
            ->values();
    }
}
