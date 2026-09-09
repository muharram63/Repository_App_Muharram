<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationNotice extends Model
{
    protected $table = 'moderation_notices';

    protected $fillable = [
        'user_id',
        'complaint_id',
        'action',
        'subject',
        'reason',
        'comment',
        'seen_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public const ACTIONS = [
        'complaint_received' => 'На ваш объект пожаловались',
        'complaint_resolved' => 'Замечание отмечено исправленным',
        'close_vacancy' => 'Вакансия снята с публикации',
        'hide_resume' => 'Резюме скрыто из каталога',
        'block_user' => 'Аккаунт заблокирован',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function title(): string
    {
        return self::ACTIONS[$this->action] ?? 'Решение модератора';
    }

    /**
     * Что делать владельцу объекта.
     */
    public function hint(): string
    {
        return match ($this->action) {
            'complaint_received' => 'Проверьте объект и отметьте замечание исправленным в разделе «Жалобы».',
            'complaint_resolved' => 'Автор жалобы увидит, что замечание устранено.',
            'close_vacancy' => 'Исправьте описание и опубликуйте вакансию заново.',
            'hide_resume' => 'Проверьте данные резюме и включите показ в настройках приватности.',
            'block_user' => 'Обратитесь в поддержку, если считаете блокировку ошибкой.',
            default => 'Обратитесь в поддержку, если считаете решение ошибкой.',
        };
    }
}
