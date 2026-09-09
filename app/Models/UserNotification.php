<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'url',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Типы уведомлений и их иконки.
     */
    public const ICONS = [
        'response' => '📨',
        'response_status' => '✅',
        'message' => '💬',
        'interview' => '🎥',
        'complaint' => '⚠️',
        'complaint_status' => '📌',
        'moderation' => '🛡',
        'admin_reply' => '📬',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function icon(): string
    {
        return self::ICONS[$this->type] ?? '🔔';
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Положить уведомление в ящик пользователя.
     * $setting — ключ настройки, которым пользователь может это отключить.
     * Имя deliver, а не push: push у Eloquent уже занят.
     */
    public static function deliver(
        ?int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?string $setting = null,
        bool $collapse = false,
        bool $read = false,
    ): ?self {
        if (! $userId) {
            return null;
        }

        $user = User::find($userId);

        if (! $user) {
            return null;
        }

        // пользователь отключил этот вид уведомлений
        if ($setting && ! $user->setting($setting)) {
            return null;
        }

        // склейка: пока прошлое такое же уведомление не прочитано, обновляем его,
        // иначе переписка на полсотни реплик забивает весь ящик.
        // Своя же запись создаётся сразу прочитанной, поэтому для неё
        // склеиваем с последней такой же, не глядя на read_at
        if ($collapse && $url) {
            $existing = self::where('user_id', $userId)
                ->where('type', $type)
                ->where('url', $url)
                ->when(! $read, fn ($q) => $q->whereNull('read_at'))
                ->latest('id')
                ->first();

            if ($existing) {
                $existing->update([
                    'title' => $title,
                    'body' => $body,
                    'created_at' => now(),
                ]);

                return $existing;
            }
        }

        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            // отправитель видит своё действие в ящике, но счётчик
            // непрочитанного из-за собственных действий не растёт
            'read_at' => $read ? now() : null,
        ]);
    }
}
