<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Сохранённый разбор совпадения вакансии и резюме.
 *
 * Живёт ровно до тех пор, пока не изменились исходные тексты: отпечаток
 * считается по тем полям, которые действительно влияют на разбор, поэтому
 * правка зарплаты или счётчика просмотров не заставляет платить за пересчёт.
 */
class MatchAnalysis extends Model
{
    protected $fillable = ['vacancy_id', 'resume_id', 'source_hash', 'payload'];

    protected $casts = ['payload' => 'array'];

    /**
     * Отпечаток исходных текстов пары.
     */
    /**
     * Версия формата разбора. Меняется вместе со структурой payload: иначе
     * сохранённые разборы старой формы читались бы новым интерфейсом молча
     * и наполовину — так было, когда подтверждённые навыки стали объектами.
     */
    private const VERSION = 3;

    public static function hash(Vacancy $vacancy, Resume $resume): string
    {
        return md5(json_encode([
            self::VERSION,
            $vacancy->title, $vacancy->description, $vacancy->skill,
            $vacancy->experience_required, $vacancy->employment_type, $vacancy->work_schedule,
            // зарплата и город теперь тоже критерии, поэтому влияют на разбор
            $vacancy->salary_to, $vacancy->city_id,
            $resume->profession, $resume->desired_position, $resume->skills,
            $resume->languages, $resume->description, $resume->experience_years,
            $resume->desired_salary, $resume->applicant?->city,
            // подтверждённые заданием навыки тоже влияют на разбор: сдал тест —
            // старый разбор устарел, иначе новый навык не был бы учтён
            SkillAttempt::badgesFor($resume->applicant)
                ->map(fn (array $b) => $b['skill'].':'.$b['score'])->all(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Разбор, если он есть и ещё не устарел.
     */
    public static function cached(Vacancy $vacancy, Resume $resume): ?self
    {
        return static::where('vacancy_id', $vacancy->id)
            ->where('resume_id', $resume->id)
            ->where('source_hash', static::hash($vacancy, $resume))
            ->first();
    }

    /**
     * Сохранить разбор поверх прежнего для этой пары.
     */
    public static function remember(Vacancy $vacancy, Resume $resume, array $payload): self
    {
        return static::updateOrCreate(
            ['vacancy_id' => $vacancy->id, 'resume_id' => $resume->id],
            ['source_hash' => static::hash($vacancy, $resume), 'payload' => $payload],
        );
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
