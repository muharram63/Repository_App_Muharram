@extends('admin.layouts.app')

@section('main')
    <style>
        .filter-radio { display: none; }
        .filters { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; align-items: center; }
        .pill {
            padding: 9px 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            font-weight: 500;
            color: #64748b;
            font-size: 14px;
        }
        #userfilter-all:checked ~ .filters [for="userfilter-all"],
        #userfilter-active:checked ~ .filters [for="userfilter-active"],
        #userfilter-inactive:checked ~ .filters [for="userfilter-inactive"],
        #userfilter-closed:checked ~ .filters [for="userfilter-closed"],
        #userfilter-archived:checked ~ .filters [for="userfilter-archived"] {
            background: #1656D6;
            color: #fff;
            border-color: #1656D6;
            box-shadow: 0 4px 12px rgba(22, 86, 214, 0.15);
        }
        .pill:hover:not(:checked) {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #334155;
        }
        .search-wrap {
            margin-right: 30vh;
            position: relative;
            flex: 1 1 260px;
            max-width: 340px;
            margin-left: auto;
        }
        .search-wrap input {
            width: 100%;
            padding: 9px 14px 9px 36px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #334155;
            outline: none;
            transition: all 0.2s ease-in-out;
            box-sizing: border-box;
        }
        .search-wrap input:focus {
            border-color: #1656D6;
            box-shadow: 0 0 0 3px rgba(22, 86, 214, 0.12);
        }
        .search-wrap svg {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            stroke: #94a3b8;
            pointer-events: none;
        }
        .no-results-row td {
            text-align: center;
            color: #94a3b8;
            padding: 24px 16px;
            font-weight: 500;
        }
    </style>

    {{-- Добавлен только display: block !important, чтобы страница просто показалась --}}
    <section class="page" id="page-users" style="display: block !important; background-color: #fcfcfd;border-radius: 2vh;">
        <div class="section-header mb-4">
            <h1 style="color: #1e293b; font-size: 28px;margin-left: 5vh;margin-top:5vh;">Вакансии</h1>
        </div>

        <form action="{{ route('superadmin.vacancy') }}" method="get"
              style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:0 3vh 20px;">
            @foreach(['all' => 'Все', 'active' => 'Активные', 'inactive' => 'Неактивные', 'closed' => 'Закрытые', 'in_archived' => 'Архивированные'] as $key => $label)
                <a class="pill" href="{{ route('superadmin.vacancy', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search ?: null])) }}"
                   style="{{ $activeStatus === $key ? 'background:#1656D6; border-color:#1656D6; color:#fff;' : '' }}">{{ $label }}</a>
            @endforeach

            <input type="text" name="q" value="{{ $search }}" placeholder="Поиск по названию, навыку или городу..."
                   style="flex:1; min-width:240px; max-width:380px; padding:9px 10px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; color:#334155;">
            @if($activeStatus !== 'all')
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif
            <button type="submit" style="border:none; background:#1656D6; color:#fff; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer;">Найти</button>
        </form>

        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; background: #fff;margin-left: 3vh;margin-right: 3vh;">
            <div class="card-body" style="padding:0;">
                <table class="table table-hover align-middle mb-0" id="users-table" style="vertical-align: middle;">
                    <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Аватар</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Работадатель</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Email</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Вакансия</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Страна</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Статус</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Зарплата</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Действия</th>

                    </tr>
                    </thead>
                    <tbody style="border-top: none;">
                    @foreach($vacancies as $vacancy)
                        @php
                            // маппинг статуса вакансии на id фильтра (in_archived -> archived)
                            $filterStatus = $vacancy->status === 'in_archived' ? 'archived' : $vacancy->status;

                            // ПРЕДПОЛОЖЕНИЕ: страна и регион берутся из полей вакансии.
                            // Если у вас они называются иначе (например, $vacancy->city,
                            // $vacancy->employer->country и т.п.) — поправьте здесь.
                            $country = $vacancy->country ?? '';
                            $region  = $vacancy->region ?? '';
                            $searchBlob = mb_strtolower($vacancy->title.' '.$country.' '.$region);
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9;" data-status="{{ $filterStatus }}" data-search="{{ $searchBlob }}">
                            <td style="font-weight: 600; color: #1e293b;">
                                @if($vacancy->employer?->user?->hasAvatar())
                                    <img src="{{ asset($vacancy->employer->user->avatar) }}" alt="Avatar" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                @else
                                    <div class="avatar text-white rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px; font-size:13px; font-weight:700; background:#1656D6;">
                                        {{ mb_substr($vacancy->employer->user->name, 0, 1) }}
                                    </div>
                                @endif
                            </td>
                            <td style="font-weight: 600; color: #1e293b;">{{$vacancy->employer->user->name}}</td>
                            <td style="font-weight: 600; color: #1e293b;">{{$vacancy->employer->user->email}}</td>
                            <td style="font-weight: 600; color: #1e293b;">{{$vacancy->title}}</td>
                            <td style="font-weight: 600; color: #1e293b;">{{ $vacancy->city->country }},{{$vacancy->city->region}}</td>
                            <td style="font-weight: 600; color: #1e293b;">
                                @switch($vacancy->status)
                                    @case('active')
                                        <span class="badge badge-active" style="text-align: center;color:darkgreen">✅ Активна</span>
                                        @break
                                    @case('inactive')
                                        <span style="text-align: center;">⏸ Неактивна</span>
                                        @break
                                    @case('closed')
                                        <span style="background:#FDECEC; color:#E14A4A;text-align: center;">✖ Закрыта</span>
                                        @break
                                    @case('in_archived')
                                        <span style="background:#F0F1F3; color:#6B7280;text-align: center;">🗄 Архивирована</span>
                                        @break
                                @endswitch</td>
                            <td style="font-weight: 600; color: #1e293b;">{{ $vacancy->salary_from }}–{{ $vacancy->salary_to }} {{ $vacancy->currencyLabel() }}</td>
                            <td>
                                <a href="{{route('superadmin.vacancy.show',$vacancy)}}" style="font-size: 2vh;margin-right: 2vh " class="btn btn-secondary">Посмотреть</a>
                            </td>


                        </tr>
                    @endforeach
                    </tbody>
                </table>

            </div>
        </div>

        <div style="margin:18px 3vh 0;">
            {{ $vacancies->links() }}
        </div>

    </section>

@endsection
