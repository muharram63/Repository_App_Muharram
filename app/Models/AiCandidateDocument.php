<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Диплом или сертификат, загруженный кандидатом.
 *
 * Резюме сюда не попадает: оно уже есть в таблице resumes, и просить загрузить
 * его повторно значило бы не доверять данным собственной площадки.
 *
 * Содержимое документа шифруется: это имя, учебное заведение, специальность и
 * даты, то есть персональные данные в чистом виде.
 */
class AiCandidateDocument extends Model
{
    protected $fillable = [
        'ai_interview_id',
        'kind',
        'path',
        'original_name',
        'mime',
        'size',
        'extracted_text',
        'parsed',
        'analysis',
        'source_hash',
        'status',
        'failure_reason',
        'analysed_at',
    ];

    protected $casts = [
        'extracted_text' => 'encrypted',
        'parsed' => 'encrypted:array',
        'analysis' => 'encrypted:array',
        'size' => 'integer',
        'analysed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'kind' => 'diploma',
    ];

    public const KINDS = [
        'diploma' => 'Диплом',
        'certificate' => 'Сертификат',
        'other' => 'Другой документ',
    ];

    /**
     * unreadable — файл открылся, но текста в нём нет (скан без текстового
     * слоя, пустой PDF). Это не ошибка системы и не вина кандидата, поэтому
     * состояние отдельное от failed.
     */
    public const STATUSES = [
        'pending' => 'Ожидает разбора',
        'analysed' => 'Разобран',
        'unreadable' => 'Не удалось прочитать',
        'failed' => 'Сбой разбора',
    ];

    /**
     * Форматы, которые принимаем. PDF и картинки читает модель, DOCX
     * распаковываем сами — расширение zip в сборке есть.
     */
    public const MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function interview()
    {
        return $this->belongsTo(AiInterview::class, 'ai_interview_id');
    }

    /**
     * Отпечаток разбора: файл и требования, по которым его проверяли.
     *
     * Пока и то и другое не изменилось, повторно платить за разбор незачем —
     * тот же приём, что у разбора совпадения (MatchAnalysis::hash).
     *
     * @param  array<int,string>  $requirements
     */
    public static function hash(string $path, array $requirements): string
    {
        sort($requirements);

        return md5(json_encode([$path, $requirements], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Разбор актуален — пересчитывать нечего.
     *
     * @param  array<int,string>  $requirements
     */
    public function isFresh(array $requirements): bool
    {
        return $this->status === 'analysed'
            && $this->source_hash === self::hash($this->path, $requirements);
    }

    public function isAnalysed(): bool
    {
        return $this->status === 'analysed';
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    /**
     * Подлинность документа система не проверяет и проверить не может.
     *
     * Строка намеренно жёстко зашита и показывается в отчёте всегда: модель
     * умеет сверить данные диплома с резюме, но не отличить настоящий документ
     * от поддельного. Обвинять кандидата в подделке она не должна ни при каких
     * обстоятельствах — только отметить, что документ требует проверки.
     */
    public const AUTHENTICITY_NOTE = 'Подлинность документа не проверена: сверены только данные с резюме.';
}
