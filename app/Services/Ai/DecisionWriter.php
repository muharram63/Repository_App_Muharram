<?php

namespace App\Services\Ai;

use App\Models\AiInterview;

/**
 * Живая формулировка решения для кандидата.
 *
 * Только формулировка. Исход, балл и причины приходят сюда уже готовыми — из
 * DecisionEngine, который считает их без всякой модели. Модель здесь ничего не
 * решает, она подбирает слова.
 *
 * Разделение жёсткое намеренно. Отказ, написанный моделью «от себя», рано или
 * поздно пообещает то, чего не было, или смягчит причину до неузнаваемости. А
 * сбой модели не должен оставлять человека без ответа — поэтому вызывающий код
 * всегда имеет готовый текст из DecisionMessage и обращается сюда лишь за тем,
 * чтобы сделать его теплее.
 */
interface DecisionWriter
{
    public function available(): bool;

    /**
     * Переписать сообщение живее, не меняя сути.
     *
     * @param  string  $outcome  passed | rejected | manual_review
     * @param  array<int,string>  $reasons  чего не хватило
     * @param  string  $fallback  готовый шаблонный текст — на него и опираемся
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function write(
        AiInterview $interview,
        string $outcome,
        array $reasons,
        string $fallback,
    ): string;
}
