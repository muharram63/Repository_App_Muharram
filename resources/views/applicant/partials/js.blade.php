{{--Личный кабинет--}}

<script>
    const titles = {
        profile:   ["Профиль", "Данные из анкеты и основная информация аккаунта"],
        employers: ["Компании", "Отклики и приглашения от работодателей"],
        responses: ["Мои отклики", "Статусы поданных заявок на вакансии"],
        settings:  ["Настройки", "Email, телефон и безопасность аккаунта"]
    };

    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            const target = item.dataset.section;

            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.getElementById('section-' + target).classList.add('active');

            document.getElementById('pageTitle').textContent = titles[target][0];
            document.getElementById('pageSubtitle').textContent = titles[target][1];
        });
    });

    document.getElementById('profileForm').addEventListener('submit', (e) => {
        e.preventDefault();
        // здесь отправка данных профиля на сервер (fetch/AJAX)
        alert('Профиль сохранён (заглушка)');
    });

    document.getElementById('logoutBtn').addEventListener('click', () => {
        // здесь очистка сессии/токена и редирект на страницу входа
        window.location.href = 'index.html';
    });
</script>
