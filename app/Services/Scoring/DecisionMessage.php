<?php

namespace App\Services\Scoring;

use App\Models\AiInterview;
use Illuminate\Support\Str;

/**
 * Что сказать кандидату.
 *
 * Здесь лежит гарантированный текст — тот, который уйдёт человеку, даже если
 * модель недоступна, исчерпана квота или ответ не прошёл проверку. Модель может
 * сделать формулировку теплее и точнее, но не может оставить кандидата без
 * ответа: сообщение существует всегда, потому что его собирает код.
 *
 * Внутренние баллы сюда не попадают ни при каких условиях. Кандидату говорят,
 * каких требований не хватило, а не «вы набрали 58 из 70»: число без контекста
 * ничего не объясняет и звучит как приговор.
 */
class DecisionMessage
{
    /**
     * Текст по исходу.
     *
     * @param  array<int,string>  $reasons  чего не хватило — словами, без цифр
     */
    public static function template(string $outcome, AiInterview $interview, array $reasons = []): string
    {
        $position = $interview->vacancy?->title ?? 'выбранную позицию';
        $sla = $interview->config?->response_sla ?? 'в ближайшее время';

        return match ($outcome) {
            DecisionEngine::PASSED => self::passed($position, $sla),
            DecisionEngine::REJECTED => self::rejected($position, $reasons),
            default => self::manual($position, $sla),
        };
    }

    private static function passed(string $position, string $sla): string
    {
        return 'Поздравляем! Вы успешно прошли собеседование и подходите на позицию «'.$position.'». '
            .'Мы свяжемся с вами '.$sla.' с деталями предложения и следующими шагами. '
            .'Спасибо за подробные ответы — с вами было интересно разговаривать.';
    }

    /**
     * @param  array<int,string>  $reasons
     */
    private static function rejected(string $position, array $reasons): string
    {
        $text = 'Спасибо, что прошли собеседование на позицию «'.$position.'» и уделили этому время. ';

        /*
         * Причина обязательна, но только корректная: чего не хватило по
         * требованиям вакансии. Отказ без причины воспринимается как отписка, а
         * названная причина хотя бы говорит человеку, куда расти.
         */
        if ($reasons !== []) {
            $text .= 'К сожалению, в этот раз мы остановились на других кандидатах: '
                .'по этой вакансии нам не хватило подтверждения по '
                .self::enumerate($reasons).'. ';
        } else {
            $text .= 'К сожалению, в этот раз мы остановились на других кандидатах. ';
        }

        $text .= 'Это не оценка вас как специалиста — речь только о совпадении с требованиями '
            .'конкретной вакансии. Посмотрите другие предложения на площадке: '
            .'ваш опыт может подойти им лучше. Успехов в поиске!';

        return $text;
    }

    private static function manual(string $position, string $sla): string
    {
        /*
         * Ни отказа, ни приглашения. Система не берётся решать, и говорить
         * человеку что-то определённое нельзя — это была бы ложь в обе стороны.
         */
        return 'Спасибо за собеседование на позицию «'.$position.'». '
            .'Ваши ответы получены и переданы работодателю: по вашей кандидатуре '
            .'решение принимает человек. Мы сообщим о результате '.$sla.'.';
    }

    /**
     * «А», «А и Б», «А, Б и В» — перечисление по-человечески.
     *
     * @param  array<int,string>  $items
     */
    private static function enumerate(array $items): string
    {
        $items = array_values(array_filter(array_map(
            fn ($item) => Str::lower(trim((string) $item)),
            array_slice($items, 0, 3),
        )));

        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' и '.$last;
    }

    /**
     * Короткая подпись исхода для списка и уведомлений.
     */
    public static function headline(string $outcome): string
    {
        return match ($outcome) {
            DecisionEngine::PASSED => 'Вы прошли отбор',
            DecisionEngine::REJECTED => 'Ответ по вашей кандидатуре',
            default => 'Ваша кандидатура на рассмотрении',
        };
    }
}
