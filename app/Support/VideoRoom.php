<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Комната видеовстречи на сервере Jitsi.
 *
 * Главный вопрос здесь — можно ли показать встречу прямо на нашей странице.
 * Это решает не наш код, а сервер Jitsi: многие публичные серверы запрещают
 * встраивание в чужие сайты заголовками X-Frame-Options и
 * Content-Security-Policy: frame-ancestors. Так делает и meet.ffmuc.net,
 * который стоит по умолчанию, — и встреча годами выглядела как пустая рамка
 * «отказано в подключении», а запасная ссылка не появлялась: она ждала
 * незагрузившегося скрипта, а скрипт загружался нормально.
 *
 * Поэтому сервер спрашивается заранее: если встраивание запрещено, страница
 * открывает комнату в новой вкладке. Там встреча работает на любом сервере.
 */
class VideoRoom
{
    /** Сколько помнить ответ сервера: заголовки меняются редко. */
    private const CACHE_HOURS = 12;

    public static function domain(): string
    {
        return (string) config('services.jitsi.domain', 'meet.ffmuc.net');
    }

    /**
     * Прямой адрес комнаты — для новой вкладки и для копирования.
     */
    public static function url(string $room): string
    {
        return 'https://'.self::domain().'/'.rawurlencode($room);
    }

    /**
     * Можно ли встроить комнату в страницу сайта с хостом $host.
     *
     * Режим задаётся JITSI_EMBED: always — всегда встраивать (свой сервер,
     * настроенный под сайт), never — всегда новая вкладка, auto — спросить
     * сервер. Сбой проверки означает «нельзя»: новая вкладка работает всегда,
     * а пустая рамка — никогда.
     */
    public static function embeddable(string $host): bool
    {
        return match (config('services.jitsi.embed', 'auto')) {
            'always' => true,
            'never' => false,
            default => self::serverAllowsEmbedding($host),
        };
    }

    private static function serverAllowsEmbedding(string $host): bool
    {
        $domain = self::domain();
        $key = 'jitsi-embed:'.$domain.':'.$host;

        $cached = Cache::get($key);

        if ($cached !== null) {
            return (bool) $cached;
        }

        try {
            $response = Http::timeout(4)->connectTimeout(3)
                ->get('https://'.$domain.'/workio-embed-check');

            $allowed = $response->successful()
                && self::headersAllow(
                    $host,
                    (string) $response->header('X-Frame-Options'),
                    (string) $response->header('Content-Security-Policy'),
                );

            Cache::put($key, $allowed, now()->addHours(self::CACHE_HOURS));
        } catch (\Throwable $e) {
            // сервер не ответил — ненадолго запоминаем «нельзя» и пробуем позже
            $allowed = false;
            Cache::put($key, false, now()->addMinutes(10));
        }

        return $allowed;
    }

    /**
     * Разрешают ли заголовки показать страницу внутри сайта $host.
     */
    public static function headersAllow(string $host, string $frameOptions, string $csp): bool
    {
        $frameOptions = Str::lower(trim($frameOptions));

        // DENY и SAMEORIGIN одинаково закрывают дверь чужому сайту
        if ($frameOptions === 'deny' || $frameOptions === 'sameorigin') {
            return false;
        }

        if (! preg_match('/frame-ancestors([^;]*)/i', $csp, $match)) {
            return true;
        }

        $sources = preg_split('/\s+/', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sources as $source) {
            $source = Str::lower(trim($source, "'"));

            if ($source === '*') {
                return true;
            }

            $pattern = preg_replace('#^[a-z]+://#', '', $source);

            if ($pattern === Str::lower($host)) {
                return true;
            }

            if (str_starts_with($pattern, '*.') && str_ends_with(Str::lower($host), substr($pattern, 1))) {
                return true;
            }
        }

        return false;
    }
}
