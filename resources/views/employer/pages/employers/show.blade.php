@extends('public.layouts.app')

@section('content')
<section class="hero">

        {{-- Глобальная анимация на фоне всей страницы --}}
        <div class="ea-bg-anims">
            <div class="ea-bg-sphere ea-bg-sphere--1"></div>
            <div class="ea-bg-sphere ea-bg-sphere--2"></div>
            <div class="ea-bg-sphere ea-bg-sphere--3"></div>
        </div>

        {{-- ================= HERO ================= --}}
        <section class="ea-hero">
            <div class="ea-hero__top">
                <a href="#" class="ea-back">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    {{ __('Все компании') }}
                </a>
                <span class="ea-badge">{{ __('Профиль работодателя') }}</span>
            </div>

            <div class="ea-hero__body">
                <div class="ea-avatar">
                    <span>J</span>
                    <svg class="ea-avatar__ring" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="56"/>
                    </svg>
                </div>

                <div class="ea-hero__text">
                    <h1 class="ea-title">{{$employer->company_name}}</h1>
                    <p class="ea-subtitle">
                        {{$employer->job}}
                        <span class="ea-dot">•</span>
                        {{$employer->city->country}} , {{$employer->city->region}}
                    </p>

                    <div class="ea-chips">
                        <span class="ea-chip">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zM20 21a8 8 0 10-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            {{ __('Контактное лицо') }}
                        </span>
                        <span class="ea-chip">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            На сервисе с {{$employer->created_at}}
                        </span>
                    </div>
                </div>

                <div class="ea-hero__cta">
                    <button class="ea-btn ea-btn--primary" type="button">{{ __('Написать работодателю') }}</button>
                    <button class="ea-btn ea-btn--ghost" type="button">{{ __('Все вакансии компании') }}</button>
                </div>
            </div>
        </section>

        {{-- ================= SIGNATURE: PIPELINE ================= --}}
        <section class="ea-pipeline" id="ea-pipeline">
            <h2 class="ea-section-title">{{ __('Как устроен найм в этой компании') }}</h2>

            <div class="ea-pipeline__track">
                <div class="ea-pipeline__line"><span class="ea-pipeline__pulse"></span></div>

                <div class="ea-node" style="--d:0">
                    <div class="ea-node__dot"></div>
                    <div class="ea-node__card">
                        <span class="ea-node__label">{{ __('Работодатель') }}</span>
                        <strong>{{$employer->user->name}}</strong>
                    </div>
                </div>

                <div class="ea-node" style="--d:1">
                    <div class="ea-node__dot"></div>
                    <div class="ea-node__card">
                        <span class="ea-node__label">{{ __('Вакансия') }}</span>
                        <strong>{{$employer->job}}</strong>
                    </div>
                </div>

                <div class="ea-node" style="--d:2">
                    <div class="ea-node__dot"></div>
                    <div class="ea-node__card">
                        <span class="ea-node__label">{{ __('Отклики') }}</span>
                        <strong>{{ __('0 кандидатов') }}</strong>
                    </div>
                </div>

                <div class="ea-node" style="--d:3">
                    <div class="ea-node__dot ea-node__dot--final"></div>
                    <div class="ea-node__card ea-node__card--final">
                        <span class="ea-node__label">{{ __('Наём') }}</span>
                        <strong>{{ __('~2.3 дня в среднем') }}</strong>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================= DETAILS ================= --}}
        <section class="ea-details">
            <div class="ea-card ea-card--about">
                <h3>{{ __('О компании') }}</h3>
                <p>{{$employer->description}}</p>
            </div>

            <div class="ea-stat-grid">
                <div class="ea-stat">
                    <div class="ea-stat__icon ea-stat__icon--blue">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <span class="ea-stat__num">1</span>
                        <span class="ea-stat__label">{{ __('активных вакансий') }}</span>
                    </div>
                </div>
                <div class="ea-stat">
                    <div class="ea-stat__icon ea-stat__icon--purple">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 100-8 4 4 0 000 8zm14 14v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <span class="ea-stat__num">0</span>
                        <span class="ea-stat__label">{{ __('кандидатов в отклике') }}</span>
                    </div>
                </div>
                <div class="ea-stat">
                    <div class="ea-stat__icon ea-stat__icon--pink">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <span class="ea-stat__num">{{ __('2.3 дня') }}</span>
                        <span class="ea-stat__label">{{ __('до первого ответа') }}</span>
                    </div>
                </div>
                <div class="ea-stat">
                    <div class="ea-stat__icon ea-stat__icon--green">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.952 11.952 0 01-7.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <span class="ea-stat__num">98%</span>
                        <span class="ea-stat__label">{{ __('проходят модерацию') }}</span>
                    </div>
                </div>
            </div>
        </section>

</section>

    <style>
        :root {
            --ea-bg: #F4F7FC;
            --ea-panel: rgba(255, 255, 255, 0.85);
            --ea-blue-500: #3B82F6;
            --ea-blue-400: #4F46E5;
            --ea-text: #1E293B;
            --ea-muted: #64748B;
            --ea-gold: #F59E0B;
            --ea-radius: 20px;
            --ea-shadow: 0 10px 35px -10px rgba(79, 70, 229, 0.06);
            --ea-shadow-hover: 0 20px 40px -15px rgba(79, 70, 229, 0.12);
        }

        .ea-page {
            position: relative;
            background: var(--ea-bg);
            color: var(--ea-text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            padding: 40px clamp(16px, 4vw, 50px) 60px;
            border-radius: 24px;
            overflow: hidden;
            z-index: 1;
        }

        /* ---------- ЖИВОЙ ФОН СТРАНИЦЫ ---------- */
        .ea-bg-anims {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }

        .ea-bg-sphere {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: ea-float-bg infinite alternate cubic-bezier(0.45, 0, 0.55, 1);
        }

        .ea-bg-sphere--1 {
            width: 450px; height: 450px;
            background: radial-gradient(circle, rgba(199, 210, 254, 1) 0%, rgba(224, 231, 255, 0) 70%);
            top: -10%; left: -5%;
            animation-duration: 20s;
        }

        .ea-bg-sphere--2 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(191, 219, 254, 0.8) 0%, rgba(239, 246, 255, 0) 70%);
            bottom: 10%; right: -5%;
            animation-duration: 25s;
            animation-delay: -5s;
        }

        .ea-bg-sphere--3 {
            width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(251, 207, 232, 0.6) 0%, rgba(253, 242, 248, 0) 70%);
            top: 40%; left: 50%;
            animation-duration: 18s;
            animation-delay: -10s;
        }

        @keyframes ea-float-bg {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(60px, -40px) scale(1.1); }
            100% { transform: translate(-30px, 50px) scale(0.95); }
        }

        /* ---------- HERO ---------- */
        .ea-hero {
            margin-left: 5vh;
            width: 200vh;
            position: relative;
            background: var(--ea-panel);
            border: 1px solid rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: var(--ea-shadow);
            border-radius: var(--ea-radius);
            padding: 36px clamp(20px, 4vw, 44px);
            animation: ea-rise .7s cubic-bezier(.2, 1, .2, 1) both;
        }

        .ea-hero__top {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 32px;
        }
        .ea-back {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--ea-muted); text-decoration: none; font-size: 14px; font-weight: 500;
            transition: color .25s ease, transform .25s ease;
        }
        .ea-back:hover { color: var(--ea-blue-400); transform: translateX(-3px); }
        .ea-badge {
            font-size: 11px; font-weight: 600; letter-spacing: .04em;
            color: var(--ea-blue-400);
            border: 1px solid rgba(79, 70, 229, 0.15);
            padding: 6px 14px; border-radius: 999px;
            background: rgba(255, 255, 255, 0.8);
        }

        .ea-hero__body {
            display: flex; gap: 32px; align-items: center; flex-wrap: wrap;
        }
        .ea-avatar {
            position: relative;
            width: 96px; height: 96px; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center;
            font-size: 38px; font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #818CF8, var(--ea-blue-400));
            border-radius: 24px;
            box-shadow: 0 12px 24px -8px rgba(79, 70, 229, 0.3);
        }
        .ea-avatar__ring {
            position: absolute; inset: -6px;
            fill: none; stroke: var(--ea-blue-400); stroke-width: 2.5;
            stroke-dasharray: 352; stroke-dashoffset: 352;
            animation: ea-draw 1.2s .4s cubic-bezier(.2, 1, .2, 1) forwards;
            opacity: 0.6;
        }

        .ea-hero__text { flex: 1 1 320px; min-width: 0; }
        .ea-title {
            font-size: clamp(24px, 3vw, 34px);
            font-weight: 700; margin: 0 0 8px; line-height: 1.2;
            color: #0F172A;
        }
        .ea-subtitle {
            color: var(--ea-muted); margin: 0 0 20px; font-size: 15px; font-weight: 500;
        }
        .ea-dot { margin: 0 8px; color: #CBD5E1; }
        .ea-chips { display: flex; gap: 10px; flex-wrap: wrap; }
        .ea-chip {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; font-weight: 500; color: #475569;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 6px 14px; border-radius: 999px;
        }
        .ea-chip svg { color: var(--ea-muted); }

        .ea-hero__cta { display: flex; flex-direction: column; gap: 12px; flex: 0 0 auto; }
        .ea-btn {
            border: none; cursor: pointer; font-size: 14px; font-weight: 600;
            padding: 14px 24px; border-radius: 14px;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease, filter .2s ease;
            white-space: nowrap; font-family: inherit;
        }
        .ea-btn--primary {
            background: linear-gradient(135deg, #4F46E5, #3B82F6);
            color: #fff; box-shadow: 0 10px 25px -6px rgba(79, 70, 229, 0.4);
        }
        .ea-btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px -6px rgba(79, 70, 229, 0.5);
            filter: brightness(1.05);
        }
        .ea-btn--ghost {
            background: rgba(255, 255, 255, 0.9); color: #4F46E5;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .ea-btn--ghost:hover {
            border-color: rgba(79, 70, 229, 0.3);
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
        }

        /* ---------- PIPELINE ---------- */
        .ea-pipeline { margin-top: 60px; }
        .ea-section-title {
            font-size: 22px; font-weight: 700; margin: 0 0 32px;
            color: #0F172A; text-align: center;
        }
        .ea-pipeline__track {
            position: relative;
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
            padding-top: 26px;
        }
        .ea-pipeline__line {
            position: absolute; top: 6px; left: 12%; right: 12%; height: 3px;
            background: #E2E8F0; border-radius: 2px;
        }
        .ea-pipeline__pulse {
            position: absolute; top: -3px; left: 0; width: 9px; height: 9px;
            border-radius: 50%; background: var(--ea-blue-500);
            box-shadow: 0 0 14px 4px rgba(59, 130, 246, 0.6);
            animation: ea-pulse-travel 5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }
        .ea-node {
            position: relative;
            display: flex; flex-direction: column; align-items: center; text-align: center;
            animation: ea-rise .6s cubic-bezier(.2, 1, .2, 1) both;
            animation-delay: calc(var(--d) * .15s + .2s);
        }
        .ea-node__dot {
            width: 14px; height: 14px; border-radius: 50%;
            background: #FFF;
            border: 3px solid var(--ea-blue-500);
            margin-bottom: 16px; z-index: 2;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            transition: transform .25s ease;
        }
        .ea-node:hover .ea-node__dot { transform: scale(1.2); }
        .ea-node__dot--final { border-color: var(--ea-gold); box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2); }

        .ea-node__card {
            background: var(--ea-panel);
            border: 1px solid rgba(226, 232, 240, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--ea-shadow);
            border-radius: 16px; padding: 16px 14px; width: 100%;
            transition: transform .3s cubic-bezier(.2, 1, .2, 1), border-color .3s ease, box-shadow .3s ease, background .3s ease;
        }
        .ea-node__card:hover {
            transform: translateY(-6px);
            border-color: rgba(79, 70, 229, 0.25);
            box-shadow: var(--ea-shadow-hover);
            background: rgba(255, 255, 255, 0.95);
        }
        .ea-node__card--final { border-color: rgba(245, 158, 11, 0.3); }
        .ea-node__label {
            display: block; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
            color: var(--ea-muted); margin-bottom: 6px;
        }
        .ea-node__card strong { font-size: 14px; font-weight: 700; color: #1E293B; }

        /* ---------- DETAILS & STATS ---------- */
        .ea-details {
            margin-top: 50px;
            display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px;
        }
        .ea-card {
            background: var(--ea-panel);
            border: 1px solid rgba(226, 232, 240, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--ea-shadow);
            border-radius: var(--ea-radius);
            padding: 28px;
            animation: ea-rise .7s .2s cubic-bezier(.2, 1, .2, 1) both;
        }
        .ea-card h3 { margin: 0 0 14px; font-size: 18px; font-weight: 700; color: #0F172A; }
        .ea-card p { margin: 0; color: var(--ea-muted); font-size: 15px; line-height: 1.7; }

        .ea-stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .ea-stat {
            background: var(--ea-panel);
            border: 1px solid rgba(226, 232, 240, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--ea-shadow);
            border-radius: 16px; padding: 20px;
            display: flex; gap: 14px; align-items: center;
            transition: transform .3s ease, box-shadow .3s ease;
            animation: ea-rise .6s cubic-bezier(.2, 1, .2, 1) both;
        }
        .ea-stat:hover {
            transform: translateY(-4px);
            box-shadow: var(--ea-shadow-hover);
        }
        .ea-stat:nth-child(1) { animation-delay: .15s }
        .ea-stat:nth-child(2) { animation-delay: .25s }
        .ea-stat:nth-child(3) { animation-delay: .35s }
        .ea-stat:nth-child(4) { animation-delay: .45s }

        .ea-stat__icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .ea-stat__icon--blue { background: #EFF6FF; color: #3B82F6; }
        .ea-stat__icon--purple { background: #F5F3FF; color: #8B5CF6; }
        .ea-stat__icon--pink { background: #FDF2F8; color: #EC4899; }
        .ea-stat__icon--green { background: #F0FDF4; color: #22C55E; }

        .ea-stat__num {
            display: block; font-size: 24px; font-weight: 700; color: #0F172A; line-height: 1.1; margin-bottom: 2px;
        }
        .ea-stat__label { font-size: 13px; font-weight: 500; color: var(--ea-muted); }

        /* ---------- KEYFRAMES ---------- */
        @keyframes ea-rise {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes ea-draw {
            from { stroke-dashoffset: 352; }
            to { stroke-dashoffset: 0; }
        }
        @keyframes ea-pulse-travel {
            0% { left: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { left: calc(100% - 9px); opacity: 0; }
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 990px) {
            .ea-details { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .ea-pipeline__track { grid-template-columns: 1fr 1fr; row-gap: 28px; }
            .ea-pipeline__line { display: none; }
            .ea-hero__cta { flex-direction: row; width: 100%; }
            .ea-btn { flex: 1; text-align: center; justify-content: center; }

            .ea-bg-sphere--1 { width: 300px; height: 300px; }
            .ea-bg-sphere--2 { width: 250px; height: 250px; }
            .ea-bg-sphere--3 { width: 200px; height: 200px; }
        }
        @media (max-width: 480px) {
            .ea-pipeline__track { grid-template-columns: 1fr; }
            .ea-stat-grid { grid-template-columns: 1fr; }
            .ea-hero__cta { flex-direction: column; }
        }
    </style>

@endsection
