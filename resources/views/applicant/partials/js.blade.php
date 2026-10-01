{{--Личный кабинет--}}

<script>
    /*
     * Остаток от статического макета, приведённый в чувство.
     *
     * Скрипт искал элементы, которых на страницах нет: #profileForm не
     * существует ни на одной из двадцати пяти страниц, где этот файл
     * подключён, и обращение к null роняло весь блок — вместе с ним не
     * доживал до регистрации и обработчик выхода. Разделы вида #section-*
     * тоже остались только на одной странице, поэтому каждый щелчок по меню
     * бросал исключение в консоль.
     *
     * Теперь каждая находка проверяется, и отсутствие элемента означает
     * «этой страницы это не касается», а не поломку.
     */
    (function () {
        const titles = {
            profile:   ["Профиль", "Данные из анкеты и основная информация аккаунта"],
            employers: ["Компании", "Отклики и приглашения от работодателей"],
            responses: ["Мои отклики", "Статусы поданных заявок на вакансии"],
            settings:  ["Настройки", "Email, телефон и безопасность аккаунта"]
        };

        document.querySelectorAll('.nav-item').forEach(function (item) {
            item.addEventListener('click', function () {
                const target = item.dataset.section;
                const section = target ? document.getElementById('section-' + target) : null;

                // пункт ведёт на другую страницу — переключать нечего,
                // переход сделает сама ссылка
                if (!section) { return; }

                document.querySelectorAll('.nav-item').forEach(function (i) {
                    i.classList.remove('active');
                });
                item.classList.add('active');

                document.querySelectorAll('.section').forEach(function (s) {
                    s.classList.remove('active');
                });
                section.classList.add('active');

                const title = document.getElementById('pageTitle');
                const subtitle = document.getElementById('pageSubtitle');

                if (title && titles[target]) { title.textContent = titles[target][0]; }
                if (subtitle && titles[target]) { subtitle.textContent = titles[target][1]; }
            });
        });
    })();
</script>
