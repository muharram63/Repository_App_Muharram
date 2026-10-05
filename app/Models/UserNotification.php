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
    /**
     * Текст уведомления, который переводится на язык получателя.
     *
     * Уведомление пишется в момент чужого действия — работодатель меняет
     * статус, очередь объявляет решение, — и язык текущего запроса здесь не
     * язык того, кто будет читать. Раньше всё уходило по-русски: человек с
     * таджикским интерфейсом получал русские уведомления. Поэтому вызывающий
     * код передаёт ключ словаря и подстановки, а переводит deliver() — на язык
     * получателя.
     *
     * @return array{0:string,1:array<string,string>}
     */
    public static function text(string $key, array $replace = []): array
    {
        return [$key, $replace];
    }

    /**
     * Строка или ключ из text() — в готовый текст на нужном языке.
     */
    private static function resolve(string|array|null $text, string $locale): ?string
    {
        if (is_array($text)) {
            [$key, $replace] = $text + [1 => []];

            // подстановки тоже могут быть ключами: статус, причина жалобы
            $replace = array_map(
                fn ($value) => is_array($value) ? self::resolve($value, $locale) : $value,
                $replace,
            );

            return __($key, $replace, $locale);
        }

        return $text;
    }

    public static function deliver(
        ?int $userId,
        string $type,
        string|array $title,
        string|array|null $body = null,
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

        $locale = in_array($user->locale, config('app.available_locales', ['ru']), true)
            ? $user->locale
            : config('app.locale');

        $title = (string) self::resolve($title, $locale);
        $body = self::resolve($body, $locale);

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
