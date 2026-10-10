<?php

namespace App\Models\Trainer;

use Illuminate\Database\Eloquent\Model;

/**
 * Направление тренировки: «PHP-разработчик», «Бухгалтер», «Учитель».
 *
 * Навыки направления становятся критериями оценки учебной вакансии — по ним
 * Анна спрашивает, а движок оценивает, ровно как на настоящем собеседовании.
 */
class TrainerTrack extends Model
{
    protected $table = 'trainer_tracks';

    protected $fillable = ['title', 'description', 'skills', 'language', 'active', 'position'];

    protected $casts = [
        'skills' => 'array',
        'active' => 'boolean',
        'position' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('position')->orderBy('title');
    }

    /**
     * Навыки в виде, пригодном для критериев: key, label, kind, weight.
     *
     * @return array<int,array{key:string,label:string,kind:string,weight:int}>
     */
    public function criteria(): array
    {
        return collect($this->skills ?? [])
            ->filter(fn ($s) => filled($s['label'] ?? null))
            ->values()
            ->map(fn ($s, $i) => [
                'key' => 'skill_'.($i + 1),
                'label' => mb_substr(trim($s['label']), 0, 120),
                'kind' => ($s['kind'] ?? 'nice') === 'must' ? 'must' : 'nice',
                'weight' => max(1, min(100, (int) ($s['weight'] ?? 20))),
            ])
            ->all();
    }
}
