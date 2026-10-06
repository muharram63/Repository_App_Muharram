{{--
    Общая шапка страниц портфолио.

    На телефоне боковое меню кабинета скрыто, а мастера портфолио заполняют
    как раз с телефона. Поэтому здесь своя полоса навигации для узкого экрана:
    в кабинет, к списку работ и добавить работу.
--}}
<style>
    .pf-mnav { display: none; gap: 8px; margin-bottom: 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .pf-mnav a {
        flex: none; padding: 9px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
        background: var(--white); border: 1px solid var(--gray-200); color: var(--ink); text-decoration: none;
    }
    .pf-mnav a.on { background: var(--indigo-600); border-color: var(--indigo-600); color: #fff; }
    .pf-flash { margin-bottom: 18px; padding: 12px 16px; border-radius: 10px; font-weight: 600; font-size: 14px; }
    .pf-flash.ok { background: var(--wash-good, #DCFCE7); color: var(--ink-good, #15803D); }
    .pf-flash.bad { background: var(--wash-danger, #FEF2F2); color: var(--ink-danger, #B91C1C); }
    .pf-flash ul { margin: 0; padding-left: 18px; }

    @media (max-width: 900px) {
        .pf-mnav { display: flex; }
        .main { padding: 16px !important; }
        .topbar { flex-wrap: wrap; gap: 12px; }
        .topbar .user-chip { display: none; }
    }
</style>

<div class="topbar">
    <div>
        <h1 id="pageTitle">{{ $title }}</h1>
        <p id="pageSubtitle">{{ $subtitle }}</p>
    </div>
    @include('partials.notification-bell')
    <div class="user-chip">
        <div class="avatar text-white d-flex align-items-center justify-content-center"
             style="font-size:13px; font-weight:700; background:#1656D6;">
            {{ $user->initials(1) }}
        </div>
        <div>
            <div class="name">{{ $user->name }}</div>
            <div class="role">{{ $user->email }}</div>
        </div>
    </div>
</div>

<nav class="pf-mnav" aria-label="{{ __('Портфолио') }}">
    <a href="{{ route('applicant.dashboard') }}">← {{ __('Кабинет') }}</a>
    <a href="{{ route('applicant.portfolio.index') }}" @class(['on' => request()->routeIs('applicant.portfolio.index')])>{{ __('Мои работы') }}</a>
    <a href="{{ route('applicant.portfolio.create') }}" @class(['on' => request()->routeIs('applicant.portfolio.create')])>+ {{ __('Добавить работу') }}</a>
</nav>

@if(session('status'))
    <div class="pf-flash ok">{{ session('status') }}</div>
@endif
@if(session('error'))
    <div class="pf-flash bad">{{ session('error') }}</div>
@endif
