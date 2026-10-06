<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Фото или видео выполненной работы.
 */
class PortfolioMedia extends Model
{
    public const DISK = 'public';

    public const STAGES = [
        'before' => 'До',
        'after' => 'После',
    ];

    protected $table = 'portfolio_media';

    protected $fillable = [
        'portfolio_work_id',
        'type',
        'stage',
        'path',
        'thumb_path',
        'poster_path',
        'mime',
        'size',
        'width',
        'height',
        'position',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (PortfolioMedia $media) {
            Storage::disk(self::DISK)->delete(array_filter([
                $media->path, $media->thumb_path, $media->poster_path,
            ]));
        });
    }

    public function work()
    {
        return $this->belongsTo(PortfolioWork::class, 'portfolio_work_id');
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /*
     * Ссылки строим через asset(), а не Storage::url(): тот берёт APP_URL из
     * .env, а сайт может открываться по другому адресу (127.0.0.1:8000 при
     * APP_URL=localhost) — и фото не загрузились бы. Так же сделаны аватары.
     */
    public static function fileUrl(string $path): string
    {
        return asset('storage/'.$path);
    }

    public function url(): string
    {
        return self::fileUrl($this->path);
    }

    /** Картинка для сетки: миниатюра фото или обложка видео. */
    public function previewUrl(): ?string
    {
        $path = $this->isVideo() ? $this->poster_path : ($this->thumb_path ?? $this->path);

        return $path ? self::fileUrl($path) : null;
    }

    public function stageLabel(): ?string
    {
        return $this->stage ? __(self::STAGES[$this->stage] ?? $this->stage) : null;
    }
}
