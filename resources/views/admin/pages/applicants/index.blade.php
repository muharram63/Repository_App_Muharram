
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
        #applicantfilter-all:checked ~ .filters [for="applicantfilter-all"],
        #applicantfilter-male:checked ~ .filters [for="applicantfilter-male"],
        #applicantfilter-female:checked ~ .filters [for="applicantfilter-female"],
        #applicantfilter-withresume:checked ~ .filters [for="applicantfilter-withresume"],
        #applicantfilter-noresume:checked ~ .filters [for="applicantfilter-noresume"] {
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

    <section class="page" id="page-applicants" style="display: block !important; background-color: #fcfcfd;border-radius: 2vh;">
        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color: #1e293b; font-size: 28px;margin-left: 5vh;margin-top: 5vh;">Соискатели</h1>
            <div class="sub text-muted" style="font-size: 0.95rem; color: #64748b;margin-right: 5vh;">Соискатели, зарегистрированные на платформе</div>
        </div>

        {{-- Фильтр по полу и наличию резюме, т.к. в таблице applicants нет поля status --}}
        <form action="{{ route('superadmin.applicants') }}" method="get"
              style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:0 3vh 20px;">
            @foreach(['all' => 'Все', 'male' => 'Мужчины', 'female' => 'Женщины', 'withresume' => 'С резюме', 'noresume' => 'Без резюме'] as $key => $label)
                <a class="pill" href="{{ route('superadmin.applicants', array_filter(['kind' => $key === 'all' ? null : $key, 'q' => $search ?: null])) }}"
                   style="{{ $activeKind === $key ? 'background:#1656D6; border-color:#1656D6; color:#fff;' : '' }}">{{ $label }}</a>
            @endforeach

            <input type="text" name="q" value="{{ $search }}" placeholder="Поиск по имени, email, городу..."
                   style="flex:1; min-width:240px; max-width:380px; padding:9px 10px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; color:#334155;">
            @if($activeKind !== 'all')
                <input type="hidden" name="kind" value="{{ $activeKind }}">
            @endif
            <button type="submit" style="border:none; background:#1656D6; color:#fff; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:600; cursor:pointer;">Найти</button>
        </form>

        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; background: #fff;margin-left: 2vh;margin-right: 3vh;">
            <div class="card-body" style="padding:0;">
                <table class="table table-hover align-middle mb-0" id="applicants-table" style="vertical-align: middle;">
                    <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Аватар</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Соискатель</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Email</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Телефон</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Город</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Возраст</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Пол</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Образование</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Резюме</th>
                        <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Действия</th>
                    </tr>
                    </thead>
                    <tbody style="border-top: none;">
                    @foreach($applicants as $applicant)
                        @php
                            $resumesCount = $applicant->resume->count();
                            $age = $applicant->birth ? $applicant->birth->age : null;

                            $searchBlob = mb_strtolower(
                                optional($applicant->user)->name.' '.
                                optional($applicant->user)->email.' '.
                                $applicant->city.' '.
                                $applicant->education.' '.
                                $applicant->resume->pluck('profession')->implode(' ').' '.
                                $applicant->resume->pluck('desired_position')->implode(' ')
                            );
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9;"
                            data-gender="{{ $applicant->gender ?? '' }}"
                            data-hasresume="{{ $resumesCount > 0 ? '1' : '0' }}"
                            data-search="{{ $searchBlob }}">
                            <td style="font-weight: 600; color: #1e293b;">
                                @if($applicant->user?->hasAvatar())
                                    <img src="{{ asset($applicant->user->avatar) }}" alt="Avatar" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                @else
                                    <div class="avatar text-white rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px; font-size:13px; font-weight:700; background:#1656D6;">
                                        {{ mb_substr(optional($applicant->user)->name ?? '—', 0, 1) }}
                                    </div>
                                @endif
                            </td>
                            <td style="font-weight: 600; color: #1e293b;">
                                {{ optional($applicant->user)->name ?? '—' }}
                                <div style="color:#94a3b8; font-size:12px; font-weight:500;">ID {{ $applicant->id }}</div>
                            </td>
                            <td style="font-weight: 600; color: #1e293b;">{{ optional($applicant->user)->email ?? '—' }}</td>
                            <td style="font-weight: 600; color: #1e293b;">{{ $applicant->phone ?? '—' }}</td>
                            <td style="font-weight: 600; color: #1e293b;">{{ $applicant->city ?? '—' }}</td>
                            <td style="font-weight: 600; color: #1e293b;">
                                @if($age !== null)
                                    {{ $age }}
                                    <span style="color:#94a3b8; font-size:12px; font-weight:500;">({{ $applicant->birth->format('d.m.Y') }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="font-weight: 600; color: #1e293b;">
                                @if($applicant->gender === 'male')
                                    <span style="background:#EAF2FF; color:#1656D6;">Мужской</span>
                                @elseif($applicant->gender === 'female')
                                    <span style="background:#FDEEF4; color:#B83280;">Женский</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="color: #475569; font-size: 13px;">
                                {{ $applicant->education ?? '—' }}
                                @if($applicant->address)
                                    <br><span style="color:#94a3b8;">{{ $applicant->address }}</span>
                                @endif
                            </td>
                            <td style="font-weight: 600; color: #1e293b;">
                                @if($resumesCount > 0)
                                    <span style="background:#EAFBF0; color:darkgreen;">{{ $resumesCount }}</span>
                                @else
                                    <span style="background:#F0F1F3; color:#6B7280;">Нет</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('superadmin.applicants.show', $applicant) }}" style="font-size: 2vh;margin-right: 2vh" class="btn btn-secondary">Посмотреть</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="no-results-row" id="no-results-row" style="display:none;">
                    <div style="padding: 24px 16px; text-align:center; color:#94a3b8; font-weight:500;">Ничего не найдено</div>
                </div>
            </div>
        </div>

        <div style="margin:18px 3vh 0;">
            {{ $applicants->links() }}
        </div>

    </section>

@endsection
