<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'reply_to_id',
        'interview_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'attachment_mime',
        'read_at',
        'edited_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function interview()
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * Вложение — картинка?
     */
    /**
     * Сколько минут после приглашения звонок ещё считается активным вызовом.
     */
    public const RING_MINUTES = 2;

    /**
     * Состояние звонка по этому приглашению:
     * ringing — идёт вызов, answered — собеседник вошёл в комнату,
     * declined — отклонён, missed — никто не ответил.
     */
    public function callState(): ?string
    {
        if (! $this->interview_id || ! $this->interview) {
            return null;
        }

        if ($this->interview->status === 'declined') {
            return 'declined';
        }

        if ($this->interview->answered_at) {
            return 'answered';
        }

        if ($this->interview->isRoomOpen()
            && $this->created_at->gte(now()->subMinutes(self::RING_MINUTES))) {
            return 'ringing';
        }

        return 'missed';
    }

    /**
     * Заголовок карточки звонка. $mine — смотрит тот, кто звонил.
     */
    public function callTitle(bool $mine): string
    {
        return $this->callState() === 'missed' && ! $mine
            ? 'Пропущенный звонок'
            : 'Видеозвонок';
    }

    /**
     * Подпись под заголовком карточки звонка.
     */
    public function callNote(bool $mine): string
    {
        return match ($this->callState()) {
            'ringing' => $mine ? 'Идёт вызов…' : 'Входящий звонок',
            'answered' => $this->interview->isRoomOpen() ? 'Комната открыта' : 'Звонок состоялся',
            'declined' => 'Звонок отклонён',
            'missed' => $mine ? 'Собеседник не ответил' : 'Вы не ответили на звонок',
            default => 'Звонок завершён',
        };
    }

    /**
     * Короткое описание для списка диалогов.
     */
    public function previewText(): string
    {
        if ($this->interview_id) {
            return $this->callState() === 'missed' ? '📵 Пропущенный звонок' : '📞 Видеозвонок';
        }

        if ($this->body) {
            return \Illuminate\Support\Str::limit($this->body, 90);
        }

        return '📎 ' . ($this->attachment_name ?: 'Файл');
    }

    public function attachmentIsImage(): bool
    {
        return $this->attachment_mime && str_starts_with($this->attachment_mime, 'image/');
    }

    /**
     * Размер вложения в удобочитаемом виде.
     */
    public function attachmentSizeLabel(): string
    {
        $bytes = max(0, (int) $this->attachment_size);

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' МБ';
        }

        // мелкие файлы показываем в байтах: округление до килобайт
        // превращало пустой файл в «1 КБ»
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }

        return round($bytes / 1024) . ' КБ';
    }

    /**
     * Сообщение, на которое отвечают.
     */
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
