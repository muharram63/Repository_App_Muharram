<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Черновик резюме, собираемый в диалоге с помощником.
 *
 * messages — история разговора для модели, data — накопленное состояние
 * резюме. Они живут отдельно намеренно: превью рисуется из data, а не
 * разбирается из текста ответов, поэтому картинка всегда согласована.
 */
class ResumeDraft extends Model
{
    /** Сколько последних реплик отдаём модели: дальше растёт только цена. */
    private const HISTORY_LIMIT = 30;

    protected $fillable = ['applicant_id', 'messages', 'data', 'stage', 'final', 'resume_id'];

    protected $casts = [
        'messages' => 'array',
        'data' => 'array',
        'final' => 'array',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }

    /**
     * Разговор доведён до опубликованного резюме.
     */
    public function finished(): bool
    {
        return $this->resume_id !== null;
    }

    /**
     * Незавершённые разговоры — с них продолжают, к ним возвращается помощник.
     */
    public function scopeUnfinished($query)
    {
        return $query->whereNull('resume_id');
    }

    /**
     * Подпись разговора в списке. Берём то, что уже известно о человеке,
     * иначе список выглядел бы как одинаковые «Черновик, Черновик, Черновик».
     */
    public function title(): string
    {
        foreach (['headline', 'desired_position', 'name'] as $field) {
            if (filled($this->data[$field] ?? null)) {
                return (string) $this->data[$field];
            }
        }

        return 'Новый разговор';
    }

    /**
     * История для запроса к модели: только последние реплики.
     *
     * @return array<int,array{role:string,text:string}>
     */
    public function turns(): array
    {
        return array_slice($this->messages ?? [], -self::HISTORY_LIMIT);
    }

    /**
     * Дописать реплику в историю. Сохранение оставляем вызывающему коду:
     * за один ход диалога добавляются сразу две реплики.
     */
    public function remember(string $role, string $text): void
    {
        $this->messages = [...($this->messages ?? []), ['role' => $role, 'text' => $text]];
    }

    /**
     * Насколько диалог продвинулся — для полосы прогресса.
     */
    public function progress(): int
    {
        $stages = ['identity', 'experience', 'achievements', 'skills', 'education', 'extras', 'done'];
        $position = array_search($this->stage, $stages, true);

        return $position === false ? 0 : (int) round($position / (count($stages) - 1) * 100);
    }
}
