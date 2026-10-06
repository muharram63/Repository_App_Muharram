<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Фото проблемы в срочной заявке: протечка, сломанная деталь.
 */
class UrgentOrderPhoto extends Model
{
    protected $fillable = [
        'urgent_order_id',
        'path',
        'thumb_path',
        'width',
        'height',
        'position',
    ];

    protected static function booted(): void
    {
        static::deleted(function (UrgentOrderPhoto $photo) {
            Storage::disk(UrgentOrder::DISK)->delete(array_filter([$photo->path, $photo->thumb_path]));
        });
    }

    public function order()
    {
        return $this->belongsTo(UrgentOrder::class, 'urgent_order_id');
    }

    /** Ссылка от адреса сайта, а не от APP_URL — как у портфолио. */
    public function url(): string
    {
        return PortfolioMedia::fileUrl($this->path);
    }

    public function thumbUrl(): string
    {
        return PortfolioMedia::fileUrl($this->thumb_path ?? $this->path);
    }
}
