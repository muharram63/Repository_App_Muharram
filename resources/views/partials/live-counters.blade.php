{{-- Живые цифры: подставляет числа в элементы с data-live, без перезагрузки страницы.
     Подключается один раз на страницу, адрес источника передаётся параметром $liveUrl.
     Страница просит только те ключи, которые на ней действительно есть. --}}
<script>
    (function () {
        const url = @json($liveUrl);
        const period = 20000;

        function targets() {
            return document.querySelectorAll('[data-live]');
        }

        function keys() {
            const set = new Set();
            targets().forEach(el => set.add(el.dataset.live));
            document.querySelectorAll('[data-live-width]').forEach(el => set.add(el.dataset.liveWidth));

            return [...set];
        }

        function paint(numbers) {
            Object.keys(numbers).forEach(function (key) {
                const value = numbers[key];

                // полосы диаграмм следуют за своим числом
                document.querySelectorAll('[data-live-width="' + key + '"]').forEach(function (el) {
                    el.style.width = value + '%';
                });

                document.querySelectorAll('[data-live="' + key + '"]').forEach(function (el) {
                    // не трогаем то, что человек прямо сейчас редактирует
                    if (el === document.activeElement) { return; }

                    const shown = String(value);

                    if (el.textContent.trim() !== shown) {
                        el.textContent = shown;
                        el.classList.add('live-bump');
                        setTimeout(function () { el.classList.remove('live-bump'); }, 900);
                    }

                    // бейджи прячутся, когда считать нечего
                    if (el.hasAttribute('data-live-hide-zero')) {
                        el.hidden = value === 0 || value === '0';
                    }
                });
            });
        }

        function pull() {
            if (document.hidden) { return; }

            const asked = keys();

            if (!asked.length) { return; }

            fetch(url + '?keys=' + encodeURIComponent(asked.join(',')), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            })
                .then(r => r.ok ? r.json() : null)
                .then(data => data && paint(data))
                .catch(() => {});
        }

        setInterval(pull, period);
        // вернулись на вкладку — обновляем сразу, не дожидаясь такта
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { pull(); }
        });
    })();
</script>

<style>
    .live-bump{
        animation: live-bump .9s ease;
    }
    @keyframes live-bump{
        0%{transform:scale(1);}
        25%{transform:scale(1.18);}
        100%{transform:scale(1);}
    }
    @media (prefers-reduced-motion: reduce){
        .live-bump{animation:none;}
    }
    [data-live][hidden]{display:none !important;}
</style>
