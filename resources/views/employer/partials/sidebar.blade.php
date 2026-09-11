{{--Личный кабинет--}}

<aside class="sidebar" id="cabinetSidebar">
    <div class="brand">
        @include('partials.brand-mark', ['brandSize' => 34])
        <div class="brand-name"><a href="{{ route('public.home') }}" style="color:inherit; text-decoration:none;">Work<span>io</span></a></div>
        {{-- сворачивает меню до полосы иконок; сама кнопка при этом остаётся на виду --}}
        <button type="button" class="side-toggle" id="sideToggle"
                aria-controls="cabinetSidebar" aria-expanded="true" title="{{ __('Свернуть меню') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
    </div>

    <div class="nav-group-label">{{ __('Кабинет') }}</div>
    <div class="nav-item active" data-section="profile">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
      </span>
        <a href="{{route('employer.dashboard')}}"> {{ __('Профиль') }} </a>
    </div>
    <div class="nav-item" data-section="employers">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
      </span>
        <a href="{{route('employer.responses.index')}}"> {{ __('Отклики соискателей') }} </a>
    </div>
    <div class="nav-item" data-section="responses">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v12H7l-3 3z"/></svg>
      </span>
        <a href="{{route('employer.vacancies.index')}}"> {{ __('Мои вакансии') }} </a>
     </div>
    <div class="nav-item" data-section="my-responses">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5h18v14H3z"/><path d="M3 7l9 6 9-6"/></svg>
      </span>
        <a href="{{route('public.responses.index')}}"> {{ __('Мои приглашения') }} </a>
    </div>
    <div class="nav-item" data-section="resumes">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h4"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
      </span>
        <a href="{{route('public.resumes.index')}}"> {{ __('Соискатели') }} </a>
    </div>
    <div class="nav-item" data-section="calls">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
      </span>
        <a href="{{route('employer.calls.index')}}"> {{ __('Звонки') }} </a>
    </div>
    @php($badges = auth()->user()->cabinetBadges())
    @php($freshComplaints = $badges['complaints'])
    <div class="nav-item" data-section="complaints">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
      </span>
        <a href="{{route('employer.complaints.index')}}"> {{ __('Жалобы') }} </a>
        <span data-live="nav-complaints" data-live-hide-zero
              style="margin-left:auto; background:#1656D6; color:#fff; font-size:11px; font-weight:700;
                     border-radius:999px; padding:2px 7px;" @if(! $freshComplaints) hidden @endif>{{ $freshComplaints }}</span>
    </div>
    @php($unreadNotifications = $badges['notifications'])
    <div class="nav-item" data-section="notifications">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </span>
        <a href="{{ route('public.notifications.index') }}"> {{ __('Уведомления') }} </a>
        <span data-live="nav-notifications" data-live-hide-zero
              style="margin-left:auto; background:#DC2626; color:#fff; font-size:11px; font-weight:700;
                     border-radius:999px; padding:2px 7px;" @if(! $unreadNotifications) hidden @endif>{{ $unreadNotifications }}</span>
    </div>
    @php($freshSupport = $badges['support'])
    <div class="nav-item" data-section="support">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </span>
        <a href="{{ route(auth()->user()->employer ? 'employer.support.index' : 'applicant.support.index') }}"> {{ __('Администратору') }} </a>
        <span data-live="nav-support" data-live-hide-zero
              style="margin-left:auto; background:#1656D6; color:#fff; font-size:11px; font-weight:700;
                     border-radius:999px; padding:2px 7px;" @if(! $freshSupport) hidden @endif>{{ $freshSupport }}</span>
    </div>
    <div class="nav-item" data-section="settings">
      <span class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/></svg>
      </span>
        <a href="{{route('employer.settings.index')}}"> {{ __('Настройки') }} </a>
    </div>

    <div class="sidebar-footer">
        <form action="{{route('logout')}}" method="post">
            @csrf
            <button class="logout-btn" id="logoutBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                {{ __('Выйти') }}
            </button>
        </form>
    </div>
</aside>

<script>
    // Сворачивание меню. Состояние держим в браузере: страницы кабинета —
    // отдельные документы, и без этого меню разворачивалось бы на каждом переходе.
    (function () {
        const app = document.querySelector('.app');
        const button = document.getElementById('sideToggle');
        if (!app || !button) return;

        // в свёрнутом виде подписи скрыты — переносим их в подсказки
        document.querySelectorAll('#cabinetSidebar .nav-item a').forEach(function (link) {
            if (!link.title) link.title = link.textContent.trim();
        });

        function apply(rail) {
            app.classList.toggle('rail', rail);
            button.setAttribute('aria-expanded', String(!rail));
            button.title = rail ? @json(__('Развернуть меню')) : @json(__('Свернуть меню'));
        }

        let rail = false;
        try { rail = localStorage.getItem('workio.sidebar') === 'rail'; } catch (e) { /* приватный режим */ }
        apply(rail);

        button.addEventListener('click', function () {
            rail = !app.classList.contains('rail');
            apply(rail);
            try { localStorage.setItem('workio.sidebar', rail ? 'rail' : 'full'); } catch (e) { /* не страшно */ }
        });
    })();
</script>


@include('partials.call-notify')
@include('partials.moderation-notices')
@include('partials.live-counters', ['liveUrl' => route('public.live.counters')])
