<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Одна выполненная работа в портфолио мастера.
 */
class PortfolioWork extends Model
{
    /** Не больше фото в одной работе. */
    public const MAX_PHOTOS = 10;

    /** Не больше видео в одной работе. */
    public const MAX_VIDEOS = 2;

    /** Не больше работ в портфолио — витрина, а не архив. */
    public const MAX_WORKS = 50;

    protected $fillable = [
        'applicant_id',
        'work_category_id',
        'title',
        'description',
        'done_at',
        'position',
    ];

    protected $casts = [
        'done_at' => 'date',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        // файлы работы удаляются вместе с ней — по папке, одним махом
        static::deleted(function (PortfolioWork $work) {
            Storage::disk(PortfolioMedia::DISK)->deleteDirectory($work->folder());
        });
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function category()
    {
        return $this->belongsTo(WorkCategory::class, 'work_category_id');
    }

    public function media()
    {
        return $this->hasMany(PortfolioMedia::class)->orderBy('position')->orderBy('id');
    }

    /** Папка с файлами работы. */
    public function folder(): string
    {
        return self::applicantFolder($this->applicant_id).'/'.$this->id;
    }

    /** Папка со всеми работами соискателя — удаляется вместе с аккаунтом. */
    public static function applicantFolder(int $applicantId): string
    {
        return 'portfolio/'.$applicantId;
    }

    /** Обложка карточки: первое «после», иначе первый снимок, иначе обложка видео. */
    public function cover(): ?PortfolioMedia
    {
        $media = $this->media;

        return $media->firstWhere('stage', 'after')
            ?? $media->firstWhere('type', 'photo')
            ?? $media->first(fn (PortfolioMedia $m) => $m->poster_path);
    }

    /** Есть ли пара «до / после». */
    public function hasBeforeAfter(): bool
    {
        return $this->media->contains('stage', 'before') && $this->media->contains('stage', 'after');
    }

    /**
     * Данные для галереи на публичных страницах — то, что нужно скрипту
     * просмотра, и ничего лишнего (никаких внутренних путей и id владельца).
     */
    public function toGallery(): array
    {
        $first = fn (string $stage) => $this->media->first(
            fn (PortfolioMedia $m) => $m->stage === $stage && ! $m->isVideo()
        );
        $before = $first('before');
        $after = $first('after');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category?->title(),
            'description' => (string) $this->description,
            'date' => $this->done_at?->translatedFormat('F Y'),
            'items' => $this->media->map(fn (PortfolioMedia $m) => [
                'type' => $m->type,
                'url' => $m->url(),
                'preview' => $m->previewUrl(),
                'stage' => $m->stageLabel(),
            ])->values()->all(),
            // пара «до / после» для ползунка — только фото
            'pair' => $before && $after ? ['before' => $before->url(), 'after' => $after->url()] : null,
        ];
    }

    public function photosCount(): int
    {
        return $this->media->where('type', 'photo')->count();
    }

    public function videosCount(): int
    {
        return $this->media->where('type', 'video')->count();
    }
}
