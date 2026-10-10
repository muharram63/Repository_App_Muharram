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
        // срочные заявки: новая заявка мастеру, отклик заказчику, выбор исполнителя
        'order' => '🛠',
        // работодателю о кандидатах: новый отклик, готовый отчёт, напоминание
        'candidate' => '👤',
        // тренажёр собеседований: оплата, разбор тренировки, возврат попытки
        'trainer' => '🏋',
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

    /**
     * Ссылка уведомления — без адреса сайта: «/orders/5», а не
     * «http://localhost/orders/5».
     *
     * route() строит полный адрес от APP_URL, когда уведомление создаёт
     * планировщик или очередь (там нет запроса браузера). Если сайт открыт по
     * другому адресу (127.0.0.1:8000 при APP_URL=localhost, или домен сменили),
     * такая ссылка не открывалась. Путь от корня работает на любом адресе.
     * Внешние ссылки (другой сайт) не трогаем.
     */
    public static function localUrl(?string $url): ?string
    {
        if (! $url) {
            return $url;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if ($host === null) {
            return $url;
        }

        $own = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            request()?->getHost(),
            'localhost',
            '127.0.0.1',
        ]);

        if (! in_array(strtolower($host), array_map('strtolower', $own), true)) {
            return $url;
        }

        return ($parts['path'] ?? '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
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
        $url = self::localUrl($url);

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
                // forceFill: created_at не входит в $fillable, и update() молча
                // пропускал его — склеенное уведомление не поднималось как новое,
                // а всплывающая карточка о нём не показывалась
                $existing->forceFill([
                    'title' => $title,
                    'body' => $body,
                    'created_at' => now(),
                ])->save();

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
