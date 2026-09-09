
@extends('admin.layouts.app')

@section('main')
    <section class="page" id="page-applicant-show" style="display: block !important; background-color: #fcfcfd; border-radius: 2vh;">

        <div class="section-header mb-4">
            <h1 class="disp font-weight-bold" style="color: #1e293b; font-size: 28px; margin-left: 5vh; margin-top: 5vh;">Карточка соискателя</h1>
            <div class="sub text-muted" style="font-size: 0.95rem; color: #64748b; margin-right: 5vh;">
                <a href="{{ route('superadmin.applicants') }}" class="btn btn-secondary" style="font-size: 1.8vh;">← Назад к списку</a>
            </div>
        </div>

        @php
            $age = $applicant->birth ? $applicant->birth->age : null;
            $genderLabel = $applicant->gender === 'male' ? 'Мужской' : ($applicant->gender === 'female' ? 'Женский' : '—');
        @endphp

        {{-- Шапка --}}
        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); background: #fff; margin: 0 3vh 3vh 2vh;">
            <div class="card-body" style="padding: 24px;">
                <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
                    @if($applicant->user?->hasAvatar())
                        <img src="{{ asset($applicant->user->avatar) }}" alt="Avatar" style="width:72px; height:72px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="avatar text-white rounded-circle d-flex align-items-center justify-content-center" style="width:72px; height:72px; font-size:26px; font-weight:700; background:#1656D6;">
                            {{ mb_substr(optional($applicant->user)->name ?? '—', 0, 1) }}
                        </div>
                    @endif
                    <div>
                        <div style="font-size:22px; font-weight:800; color:#1e293b;">{{ optional($applicant->user)->name ?? '—' }}</div>
                        <div style="color:#64748b; font-size:14px; margin-top:4px;">
                            ID {{ $applicant->id }} · {{ optional($applicant->user)->email ?? '—' }}
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:18px; margin-top:26px; border-top:1px solid #e2e8f0; padding-top:22px;">
                    <div>
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; font-weight:600;">Телефон</div>
                        <div style="font-size:15px; font-weight:600; color:#1e293b; margin-top:4px;">{{ $applicant->phone ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; font-weight:600;">Город</div>
                        <div style="font-size:15px; font-weight:600; color:#1e293b; margin-top:4px;">{{ $applicant->city ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; font-weight:600;">Пол</div>
                        <div style="font-size:15px; font-weight:600; color:#1e293b; margin-top:4px;">{{ $genderLabel }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; font-weight:600;">Дата рождения</div>
                        <div style="font-size:15px; font-weight:600; color:#1e293b; margin-top:4px;">
                            @if($applicant->birth)
                                {{ $applicant->birth->format('d.m.Y') }}
                                <span style="color:#94a3b8; font-weight:500;">({{ $age }})</span>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; font-weight:600;">Адрес</div>
                        <div style="font-size:15px; font-weight:600; color:#1e293b; margin-top:4px;">{{ $applicant->address ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; font-weight:600;">Дата регистрации</div>
                        <div style="font-size:15px; font-weight:600; color:#1e293b; margin-top:4px;">{{ $applicant->created_at ? $applicant->created_at->format('d.m.Y') : '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Образование и о себе --}}
        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); background: #fff; margin: 0 3vh 3vh 2vh;">
            <div class="card-header" style="padding:16px 24px; border-bottom:1px solid #e2e8f0; font-weight:700; color:#1e293b;">Образование</div>
            <div class="card-body" style="padding:20px 24px; color:#475569; font-size:14px;">
                {{ $applicant->education ?? '—' }}
            </div>
            <div class="card-header" style="padding:16px 24px; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; font-weight:700; color:#1e293b;">О себе</div>
            <div class="card-body" style="padding:20px 24px; color:#475569; font-size:14px; line-height:1.6;">
                {{ $applicant->about_me ?? '—' }}
            </div>
        </div>

        {{-- Резюме соискателя --}}
        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); background: #fff; margin: 0 3vh 4vh 2vh; overflow:hidden;">
            <div class="card-header" style="padding:16px 24px; border-bottom:1px solid #e2e8f0; font-weight:700; color:#1e293b;">
                Резюме ({{ $applicant->resume->count() }})
            </div>
            <div class="card-body" style="padding:0;">
                @if($applicant->resume->count() > 0)
                    <table class="table table-hover align-middle mb-0" style="vertical-align: middle;">
                        <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Профессия</th>
                            <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Желаемая должность</th>
                            <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Опыт</th>
                            <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Зарплата</th>
                            <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Обновлено</th>
                            <th style="color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Действия</th>
                        </tr>
                        </thead>
                        <tbody style="border-top: none;">
                        @foreach($applicant->resume as $resume)
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="font-weight: 600; color: #1e293b;">{{ $resume->profession }}</td>
                                <td style="font-weight: 600; color: #1e293b;">{{ $resume->desired_position }}</td>
                                <td style="color: #475569;">{{ $resume->experience_years }} г.</td>
                                <td style="font-weight: 600; color: #1e293b;">{{ number_format($resume->desired_salary, 0, ',', ' ') }} сомони</td>
                                <td style="color: #94a3b8;">{{ $resume->updated_at ? $resume->updated_at->format('d.m.Y') : '—' }}</td>
                                <td>
                                    <a href="{{ route('superadmin.resumes.show', $resume) }}" class="btn btn-secondary" style="font-size: 1.8vh;">Посмотреть</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div style="padding: 24px 16px; text-align:center; color:#94a3b8; font-weight:500;">У соискателя пока нет резюме</div>
                @endif
            </div>
        </div>

    </section>
@endsection
