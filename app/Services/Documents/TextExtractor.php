<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Извлечение текста из документов кандидата.
 *
 * Разделение труда простое: что умеет прочитать сервер — читает сервер, что не
 * умеет — читает модель.
 *
 * PDF и сканы уходят в модель целиком, и это проверено живыми вызовами:
 * Interactions API принимает блок типа document с inline base64, а модель
 * читает и текстовый PDF, и картинку с текстом. Поэтому ни OCR-пакета, ни
 * разборщика PDF в проекте не появилось — их пришлось бы ставить и
 * поддерживать, а модель уже умеет и то и другое, включая рукописные сканы,
 * которых tesseract бы не взял.
 *
 * DOCX наоборот разбираем сами: это zip с XML внутри, расширение zip в сборке
 * есть, и платить модели за распаковку архива незачем.
 */
class TextExtractor
{
    /** Диск, на котором лежат документы кандидатов. */
    public const DISK = 'local';

    /**
     * Сколько знаков текста оставляем. Резюме и диплом в этот предел
     * укладываются с большим запасом, а всё сверх него — обычно склеенный
     * мусор от неудачной вычитки, и платить за него токенами не нужно.
     */
    public const LIMIT = 20000;

    /**
     * Форматы, которые читает модель. MIME проверены живыми вызовами.
     */
    public const MODEL_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    /**
     * Форматы, которые разбирает сервер.
     */
    public const LOCAL_MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    /**
     * Разобрать документ.
     *
     * @return array{text:?string,method:string,readable:bool,reason:?string}
     *   method: local — прочитал сервер, model — прочитает модель на разборе,
     *   none — прочитать нечем
     */
    public function extract(string $path, ?string $mime): array
    {
        $mime = (string) $mime;

        if ($this->readableByModel($mime)) {
            // Текст не извлекаем: модель прочитает сам файл и увидит больше —
            // печати, подписи, расположение полей. Вытаскивать текст заранее
            // значило бы отдать ей худшие данные, чем есть.
            return ['text' => null, 'method' => 'model', 'readable' => true, 'reason' => null];
        }

        if ($mime === 'text/plain') {
            return $this->wrap($this->readPlain($path), 'local');
        }

        if ($this->isDocx($mime)) {
            return $this->wrap($this->readDocx($path), 'local');
        }

        /*
         * Старый .doc не разбираем. Это двоичный формат Word 97, для него нужен
         * отдельный разборщик, а модель его не принимает. Честно говорим
         * кандидату, что нужен другой формат, — молча сохранённый и никем не
         * прочитанный файл хуже отказа.
         */
        return [
            'text' => null,
            'method' => 'none',
            'readable' => false,
            'reason' => 'Формат не поддерживается: пересохраните документ в PDF или DOCX.',
        ];
    }

    /**
     * Модель прочитает этот формат сама.
     */
    public function readableByModel(?string $mime): bool
    {
        return in_array((string) $mime, self::MODEL_MIMES, true);
    }

    /**
     * Текст DOCX.
     *
     * Абзацы заменяем переводами строк ДО вырезания тегов: иначе слова из
     * соседних абзацев склеиваются в одно, и «инженер» с «Душанбе» становятся
     * «инженерДушанбе». Модель такое читает, но хуже, а человек в отчёте — тем
     * более.
     */
    private function readDocx(string $path): array
    {
        $full = Storage::disk(self::DISK)->path($path);

        if (! is_file($full)) {
            return ['text' => null, 'reason' => 'Файл не найден на диске.'];
        }

        $zip = new ZipArchive;

        if ($zip->open($full) !== true) {
            return ['text' => null, 'reason' => 'Файл повреждён: архив не открывается.'];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return ['text' => null, 'reason' => 'В файле нет текста документа.'];
        }

        // разрывы абзацев, строк и табуляции — в понятные пробелы и переводы
        $xml = preg_replace('~</w:p>~', "\n", $xml) ?? $xml;
        $xml = preg_replace('~<w:br[^>]*/?>~', "\n", $xml) ?? $xml;
        $xml = preg_replace('~<w:tab[^>]*/?>~', ' ', $xml) ?? $xml;

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return ['text' => $this->tidy($text), 'reason' => null];
    }

    private function readPlain(string $path): array
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            return ['text' => null, 'reason' => 'Файл не найден на диске.'];
        }

        $raw = (string) Storage::disk(self::DISK)->get($path);

        // Файл мог прийти не в UTF-8: Блокнот на русской Windows по-прежнему
        // пишет в cp1251, и без перекодировки текст выглядел бы крокозябрами.
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
        }

        return ['text' => $this->tidy($raw), 'reason' => null];
    }

    /**
     * Причёсывание: лишние пробелы и пустые строки в документах бывают
     * десятками, а платятся они токенами.
     */
    private function tidy(string $text): string
    {
        $text = str_replace("\r", '', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return Str::limit(trim($text), self::LIMIT, '');
    }

    /**
     * @param  array{text:?string,reason:?string}  $result
     * @return array{text:?string,method:string,readable:bool,reason:?string}
     */
    private function wrap(array $result, string $method): array
    {
        $text = $result['text'] ?? null;

        // Пустой текст — не ошибка чтения, а пустой документ. Для разбора это
        // одно и то же: читать нечего, и кандидату стоит сказать прямо.
        if (blank($text)) {
            return [
                'text' => null,
                'method' => $method,
                'readable' => false,
                'reason' => $result['reason'] ?? 'В документе не нашлось текста.',
            ];
        }

        return ['text' => $text, 'method' => $method, 'readable' => true, 'reason' => null];
    }

    private function isDocx(string $mime): bool
    {
        return $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
}
