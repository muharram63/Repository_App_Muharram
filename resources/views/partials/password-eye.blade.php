{{--
    Глазок для полей пароля.

    Подключается один раз на страницу и сам навешивает кнопку на каждое
    input[type="password"]. Так сделано намеренно: полей семь на трёх
    страницах (сброс пароля, подтверждение пароля, настройки обеих ролей),
    и правка разметки у каждого заняла бы больше места, чем сам переключатель.

    На регистрации свой глазок — он свёрстан вместе с индикатором надёжности
    пароля, и этот партиал туда не подключается.

    Подключать в самом конце страницы: скрипт работает по готовому DOM.
--}}
<style>
    .pass-eye-wrap{ position:relative; display:block; }
    /* класс вешает скрипт — так правило переживает любой порядок стилей */
    .pass-eye-wrap input.pass-eye-input{ padding-right:40px; }
    .pass-eye{
        position:absolute; right:12px; top:50%; transform:translateY(-50%);
        display:flex; padding:0; border:none; background:none; cursor:pointer;
        line-height:0; color:var(--text-muted, #6B7184);
        transition:color .15s ease;
    }
    .pass-eye:hover{ color:var(--text, #171B2C); }
    .pass-eye svg{ width:17px; height:17px; }
</style>

<script>
    (function () {
        const EYE_OPEN = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        const EYE_OFF = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

        const labelShow = @json(__('Показать пароль'));
        const labelHide = @json(__('Скрыть пароль'));

        document.querySelectorAll('input[type="password"]').forEach(function (field) {
            // на случай, если партиал подключили дважды
            if (field.closest('.pass-eye-wrap')) return;

            const wrap = document.createElement('div');
            wrap.className = 'pass-eye-wrap';
            field.parentNode.insertBefore(wrap, field);
            wrap.appendChild(field);
            field.classList.add('pass-eye-input');

            const button = document.createElement('button');
            // без type кнопка внутри формы отправляла бы её по клику
            button.type = 'button';
            button.className = 'pass-eye';
            button.setAttribute('aria-label', labelShow);
            button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
                + EYE_OPEN + '</svg>';
            wrap.appendChild(button);

            button.addEventListener('click', function () {
                const hidden = field.type === 'password';

                field.type = hidden ? 'text' : 'password';
                button.querySelector('svg').innerHTML = hidden ? EYE_OFF : EYE_OPEN;
                button.setAttribute('aria-label', hidden ? labelHide : labelShow);
            });
        });
    })();
</script>
