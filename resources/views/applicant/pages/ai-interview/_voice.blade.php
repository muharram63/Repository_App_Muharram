{{--
    Голосовой режим собеседования: ИИ говорит, кандидат отвечает голосом.

    Как в живом разговоре: Анна договорила вопрос — микрофон включается сам,
    кандидат отвечает, текст появляется в поле ответа, после паузы ответ
    уходит сам, и Анна отвечает следующим вопросом. Клавиатура остаётся:
    начал печатать — автоотправка выключается, отправка кнопкой.

    Распознаёт речь браузер (Chrome, Edge — по-русски и по-английски, текст
    виден сразу). Где браузер не умеет — Firefox, Safari, таджикский, —
    страница записывает ответ сама и отдаёт серверу на распознавание.

    Подключается внутри скрипта чата: использует его text, form, paintLeft.
--}}
<div class="ch-voice" id="chVoice">
    <button type="button" class="ch-btn ch-voice-toggle" id="chVoiceToggle" aria-pressed="false">
        🎙 <span id="chVoiceToggleLabel">{{ __('Включить голосовой режим') }}</span>
    </button>
    {{-- статус — тот же, что у аватара: его пишет только аватар (data-ai-state) --}}
    <span class="ch-voice-status" data-ai-state="idle"></span>
    <span class="ch-voice-state" id="chVoiceState"></span>
    <span class="ch-voice-level" id="chVoiceLevelWrap" hidden><i id="chVoiceLevel"></i></span>
    <button type="button" class="ch-btn ch-btn-ghost" id="chVoiceSend" hidden>{{ __('Готово, отправить') }}</button>
</div>
