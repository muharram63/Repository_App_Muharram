<?php

namespace App\Support;

/**
 * Есть ли в ответе кандидата код, который стоит запустить.
 *
 * Код проверялся только в заданиях формата «code». Если кандидат писал код
 * прямо в ответе на вопрос Анны или решал кодом кейс и расчёт, оценщик его
 * только читал — а правдоподобный код от работающего на глаз не отличить.
 * Теперь в таких ответах код запускается так же, как в задании.
 *
 * Признаков нужно несколько: одна фигурная скобка или точка с запятой в
 * обычной речи встречаются, а вот блок в ```, объявление функции рядом со
 * скобками или SELECT … FROM — уже код.
 */
class CodeDetector
{
    private const SIGNALS = [
        // блок кода в разметке — сам по себе достаточный признак
        '/```/' => 3,
        '/<\?php/i' => 3,
        '/\b(function|def|class|fn|func|public|private|static|const|let|var|import|from|require|include|namespace|use)\s+[\w\\\\$]+/u' => 1,
        '/\bSELECT\b[\s\S]{1,200}\bFROM\b/i' => 2,
        '/\b(INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|CREATE\s+TABLE)\b/i' => 2,
        '/\$[a-z_]\w*\s*(=|->|\[)/i' => 1,
        '/\b(return|echo|print|console\.log|System\.out)\b[^\n]*[;)]/u' => 1,
        '/\b(if|for|foreach|while)\s*\(.*\)\s*[{:]?/u' => 1,
        '/=>|->|::|===|!==|\+\+|&&|\|\|/' => 1,
        '/[{}]\s*$/m' => 1,
        '/;\s*$/m' => 1,
    ];

    /** Сколько признаков нужно, чтобы считать ответ кодом. */
    private const THRESHOLD = 3;

    public static function looksLikeCode(string $text): bool
    {
        $score = 0;

        foreach (self::SIGNALS as $pattern => $weight) {
            if (preg_match($pattern, $text)) {
                $score += $weight;

                if ($score >= self::THRESHOLD) {
                    return true;
                }
            }
        }

        return false;
    }
}
