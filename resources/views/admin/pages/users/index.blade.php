@extends('admin.layouts.app')

@section('main')
    <style>
        .us-wrap{padding:22px 24px 40px;}
        .us-head{display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;}
        .us-head h1{margin:0; font-size:24px; font-weight:800; color:#1e293b;}
        .us-head .sub{margin-top:4px; font-size:13px; color:#94a3b8;}

        .us-tools{display:flex; flex-direction:column; gap:12px; margin-bottom:18px;}
        .us-row{display:flex; flex-wrap:wrap; gap:8px; align-items:center;}
        .us-row .lbl{
            font-size:11.5px; text-transform:uppercase; letter-spacing:.5px;
            color:#94a3b8; font-weight:700; margin-right:4px;
        }
        .us-pill{
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 15px; background:#fff; border:1px solid #e2e8f0; border-radius:999px;
            font-size:13px; font-weight:600; color:#64748b; text-decoration:none;
            transition:all .15s ease;
        }
        .us-pill:hover{border-color:#cbd5e1; color:#334155;}
        .us-pill.on{background:#1656D6; border-color:#1656D6; color:#fff;}
        .us-pill .n{font-size:11.5px; opacity:.7; font-weight:700;}

        .us-search{display:flex; gap:8px; align-items:center; flex-wrap:wrap;}
        .us-search input{
            min-width:280px; padding:9px 13px; border:1px solid #e2e8f0; border-radius:9px;
            font-family:inherit; font-size:14px; color:#334155;
        }
        .us-search input:focus{outline:none; border-color:#1656D6;}
        .us-search button{
            border:none; background:#1656D6; color:#fff; border-radius:9px;
            padding:10px 18px; font-family:inherit; font-size:13.5px; font-weight:600; cursor:pointer;
        }
        .us-search a.reset{font-size:13px; color:#64748b; text-decoration:none;}

        .us-card{background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;}
        .us-count{padding:13px 18px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#64748b; font-weight:600;}
        .us-table{width:100%; border-collapse:collapse; font-size:14px;}
        .us-table thead th{
            text-align:left; padding:13px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0;
            font-size:11.5px; text-transform:uppercase; letter-spacing:.5px; color:#475569; font-weight:700;
        }
        .us-table tbody td{padding:13px 16px; border-bottom:1px solid #f1f5f9; vertical-align:middle;}
        .us-table tbody tr:last-child td{border-bottom:none;}
        .us-table tbody tr:hover{background:#fafbfc;}
        .us-name{font-weight:600; color:#1e293b;}
        .us-mail{color:#64748b;}
        .us-ava{width:40px; height:40px; border-radius:50%; object-fit:cover;}
        .us-ava-fallback{
            width:40px; height:40px; border-radius:50%; background:#1656D6; color:#fff;
            display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700;
        }
        .us-badge{padding:5px 11px; border-radius:6px; font-size:12.5px; font-weight:600; white-space:nowrap;}
        .us-badge.applicant{background:#f1f5f9; color:#475569;}
        .us-badge.employer{background:#eff6ff; color:#1d4ed8;}
        .us-badge.admin{background:#fdf4ff; color:#86198f;}
        .us-badge.active{background:#f0fdf4; color:#15803d;}
        .us-badge.blocked{background:#fef2f2; color:#b91c1c;}
        .us-acts{display:flex; gap:8px; white-space:nowrap;}
        .us-btn{
            display:inline-flex; align-items:center; gap:6px; padding:7px 13px; border-radius:8px;
            font-size:13px; font-weight:600; text-decoration:none; cursor:pointer; border:1px solid transparent;
        }
        .us-btn.open{background:#eff6ff; color:#1d4ed8; border-color:#dbeafe;}
        .us-btn.del{background:#fef2f2; color:#b91c1c; border-color:#fee2e2;}
        .us-empty{padding:56px 18px; text-align:center; color:#94a3b8;}
        .us-flash{
            margin-bottom:16px; padding:12px 15px; border-radius:10px;
            background:#f0fdf4; color:#15803d; font-size:13px; font-weight:600;
        }
        .us-error{background:#fef2f2; color:#b91c1c;}
    </style>

    @php
        $roles = ['applicant' => 'Соискатель', 'employer' => 'Работодатель', 'admin' => 'Администратор'];
        $rolePills = ['all' => 'Все', 'applicant' => 'Соискатели', 'employer' => 'Работодатели', 'admin' => 'Админы'];
        $statusPills = ['all' => 'Любой доступ', 'active' => 'Открыт', 'blocked' => 'Заблокирован'];
        $filtered = $filterRole !== 'all' || $filterStatus !== 'all' || $search !== '';
    @endphp

    <section class="us-wrap">
        <div class="us-head">
            <div>
                <h1>Пользователи</h1>
                <div class="sub">Всего на площадке: <span data-live="users-all">{{ $roleCounts['all'] }}</span> · заблокировано: <span data-live="users-blocked">{{ $blockedCount }}</span></div>
            </div>
        </div>

        @if(session('status'))
            <div class="us-flash">{{ session('status') }}</div>
        @endif

        @if(session('error'))
            <div class="us-flash us-error">{{ session('error') }}</div>
        @endif

        <div class="us-tools">
            <div class="us-row">
                <span class="lbl">Роль</span>
                @foreach($rolePills as $key => $label)
                    <a class="us-pill {{ $filterRole === $key ? 'on' : '' }}"
                       href="{{ route('superadmin.users', array_filter([
                            'role' => $key === 'all' ? null : $key,
                            'status' => $filterStatus === 'all' ? null : $filterStatus,
                            'q' => $search ?: null,
                       ])) }}">
                        {{ $label }}<span class="n" data-live="users-{{ $key }}">{{ $roleCounts[$key] }}</span>
                    </a>
                @endforeach
            </div>

            <div class="us-row">
                <span class="lbl">Доступ</span>
                @foreach($statusPills as $key => $label)
                    <a class="us-pill {{ $filterStatus === $key ? 'on' : '' }}"
                       href="{{ route('superadmin.users', array_filter([
                            'role' => $filterRole === 'all' ? null : $filterRole,
                            'status' => $key === 'all' ? null : $key,
                            'q' => $search ?: null,
                       ])) }}">{{ $label }}</a>
                @endforeach
            </div>

            <form class="us-search" action="{{ route('superadmin.users') }}" method="get">
                @if($filterRole !== 'all')
                    <input type="hidden" name="role" value="{{ $filterRole }}">
                @endif
                @if($filterStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $filterStatus }}">
                @endif

                <input type="text" name="q" value="{{ $search }}" placeholder="Поиск по имени или email...">
                <button type="submit">Найти</button>

                @if($filtered)
                    <a class="reset" href="{{ route('superadmin.users') }}">Сбросить</a>
                @endif
            </form>
        </div>

        <div class="us-card">
            <div class="us-count">
                @if($filtered)
                    Найдено: {{ $users->count() }} из {{ $roleCounts['all'] }}
                @else
                    Показаны все {{ $users->count() }}
                @endif
            </div>

            <table class="us-table">
                <thead>
                <tr>
                    <th style="width:64px;"></th>
                    <th>Пользователь</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Доступ</th>
                    <th>Регистрация</th>
                    <th style="text-align:right;">Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            @if($user->hasAvatar())
                                <img class="us-ava" src="{{ asset($user->avatar) }}" alt="">
                            @else
                                <div class="us-ava-fallback">{{ $user->initials(2) }}</div>
                            @endif
                        </td>
                        <td class="us-name">{{ $user->name }}</td>
                        <td class="us-mail">{{ $user->email }}</td>
                        <td>
                            <span class="us-badge {{ $user->role }}">{{ $roles[$user->role] ?? $user->role }}</span>
                        </td>
                        <td>
                            @if($user->isBlocked())
                                <span class="us-badge blocked">Заблокирован</span>
                            @else
                                <span class="us-badge active">Открыт</span>
                            @endif
                        </td>
                        <td class="us-mail">
                            {{ $user->date_register?->format('d.m.Y') ?? $user->created_at?->format('d.m.Y') ?? '—' }}
                        </td>
                        <td>
                            <div class="us-acts" style="justify-content:flex-end;">
                                {{-- карточка только на просмотр: данные правит сам пользователь --}}
                                <a class="us-btn open" href="{{ route('superadmin.users.edit', $user) }}">
                                    <i class="fa fa-eye"></i> Открыть
                                </a>

                                <form action="{{ route('superadmin.users.destroy', $user) }}" method="post"
                                      onsubmit="return confirm('Удалить пользователя {{ $user->name }}? Вместе с ним удалятся его анкета, вакансии и резюме.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="us-btn del" type="submit">
                                        <i class="fa fa-trash"></i> Удалить
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="us-empty">
                            <div style="font-size:15px; font-weight:600; color:#475569; margin-bottom:6px;">
                                Никого не нашли
                            </div>
                            Измените фильтры или очистите поиск
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
