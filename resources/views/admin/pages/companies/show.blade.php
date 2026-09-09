@extends('admin.layouts.app')

@section('main')
    <style>
        .profile-banner {
            margin-left: 3vh;
            width: 157vh;
            background: linear-gradient(135deg, #1656D6, #2F6FEF);
            border-radius: 16px;
            padding: 32px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .profile-banner::after {
            content: "";
            position: absolute;
            right: -40px;
            top: -40px;
            width: 180px;
            height: 180px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .profile-avatar-lg {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.4);
            flex-shrink: 0;
        }
        .profile-avatar-lg-fallback {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .profile-banner h2 {
            margin: 0 0 4px;
            font-size: 24px;
            font-weight: 800;
        }
        .profile-banner .meta {
            font-size: 14px;
            opacity: 0.9;
        }

        .stat-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
            max-width: 140vh;
            margin-left: 3vh;
        }
        .stat-card {
            width: 50vh;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
        }
        .stat-card .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #EEF3FE;
            color: #1656D6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            margin-bottom: 12px;
        }
        .stat-card .label {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .stat-card .value {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            word-break: break-word;
        }

        .detail-card {
            width: 157vh;
            margin-left: 3vh;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }
        .detail-card .head {
            padding: 16px 22px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 700;
            font-size: 14px;
            color: #1e293b;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .detail-grid .item {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
        }
        .detail-grid .item:nth-last-child(-n+2) { border-bottom: none; }
        .detail-grid .item .k {
            font-size: 11.5px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .detail-grid .item .v {
            font-size: 14.5px;
            color: #1e293b;
            font-weight: 600;
        }

        .action-bar {
            margin-left: 100vh;
            margin-bottom: 3vh;
            display: flex;
            gap: 12px;
            margin-top: 3vh;
        }
    </style>

    <section class="page" id="page-company-show" style="display: block !important; background-color: #fcfcfd; border-radius: 2vh;">

        <div class="section-header mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="disp font-weight-bold" style="color: #1e293b; font-size: 26px; margin:3vh;">Профиль компании</h1>
            </div>
        </div>

        {{-- Баннер профиля --}}
        <div class="profile-banner">
            @if($company->user?->hasAvatar())
                <img src="{{ asset($company->user->avatar) }}" alt="Avatar" class="profile-avatar-lg">
            @else
                <div class="profile-avatar-lg-fallback">{{ mb_substr($company->user->name, 0, 1) }}</div>
            @endif
            <div>
                <h2>{{ $company->company_name }}</h2>
                <div class="meta">{{ $company->user->name }} · {{ $company->user->email }}</div>
            </div>
        </div>

        {{-- Карточки со сводкой --}}
        <div class="stat-cards">
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-building"></i></div>
                <div class="label">Компания</div>
                <div class="value">{{ $company->company_name }}</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-user"></i></div>
                <div class="label">Владелец</div>
                <div class="value">{{ $company->user->name }}</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-location-dot"></i></div>
                <div class="label">Локация</div>
                <div class="value">{{ $company->city->country }}, {{ $company->city->region }}</div>
            </div>
        </div>

        {{-- Подробная информация --}}
        <div class="detail-card">
            <div class="head">Детали</div>
            <div class="detail-grid">
                <div class="item">
                    <div class="k">Название компании</div>
                    <div class="v">{{ $company->company_name }}</div>
                </div>
                <div class="item">
                    <div class="k">Владелец аккаунта</div>
                    <div class="v">{{ $company->user->name }}</div>
                </div>
                <div class="item">
                    <div class="k">Рабочий Email владельца</div>
                    <div class="v">{{ $company->email_company }}</div>
                </div>
                <div class="item">
                    <div class="k">Страна / Регион</div>
                    <div class="v">{{ $company->city->country }}, {{ $company->city->region }}</div>
                </div>
            </div>
        </div>

        {{-- Действия --}}
        <div class="action-bar">
            <a href="{{ route('superadmin.companies') }}" class="btn btn-secondary">← Назад к списку</a>

        </div>

    </section>
@endsection
