{{--
    Зелёная плашка «уведомление отправлено».

    Работодатель видит с одного взгляда, что ответ ушёл и какой: приём и отказ
    подписаны по-разному. Текст собирается из базы (AiDecision::sentBanner),
    поэтому плашка не исчезает ни сама, ни после перезагрузки страницы. Пустой
    блок #crSentBanner остаётся на странице и тогда, когда отправки ещё не было:
    в него обсуждение с Анной вписывает плашку сразу после отправки.

    $decision — последнее решение по собеседованию или null
--}}
<style>
    .cr-sent {
        display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px;
        background: var(--wash-good, #DCFCE7); color: var(--ink-good, #15803D);
        border: 1px solid #86EFAC; font-weight: 700; font-size: 15px; line-height: 1.45;
    }
    .cr-sent-icon {
        flex: none; width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center;
        background: #16A34A; color: #fff; font-size: 17px;
    }
    .cr-failed {
        display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px;
        background: var(--wash-danger, #FEF2F2); color: var(--danger, #DC2626);
        border: 1px solid #FCA5A5; font-weight: 600; font-size: 14px;
    }
    .cr-sent[hidden], .cr-failed[hidden] { display: none; }
</style>

@php($sentText = $decision?->sentBanner())
<div id="crSentBanner" class="cr-sent" role="status" @unless($sentText) hidden @endunless>
    <span class="cr-sent-icon" aria-hidden="true">✓</span>
    {{-- галочка — в значке; в тексте её нет, чтобы не повторялась --}}
    <span id="crSentText">{{ $sentText ? ltrim($sentText, '✓ ') : '' }}</span>
</div>
