@extends('public.layouts.app')
@section('content')

    <style>
        .ms-page { padding: 32px 0 56px; }
        .ms-page .container { max-width: 1100px; }
        .ms-head {
            display: flex; gap: 20px; align-items: center; flex-wrap: wrap;
            background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 24px;
            margin-bottom: 22px;
        }
        .ms-photo, .ms-photo-fallback {
            width: 88px; height: 88px; border-radius: 50%; flex: none; object-fit: cover;
            background: var(--accent-soft); color: var(--accent-text); display: flex; align-items: center; justify-content: center;
            font-size: 30px; font-weight: 800;
        }
        .ms-main { flex: 1; min-width: 220px; }
        .ms-main h1 { font-size: clamp(22px, 3vw, 30px); font-weight: 800; margin: 0 0 6px; color: var(--text); overflow-wrap: anywhere; }
        .ms-sub { color: var(--text-muted); font-size: 14px; display: flex; gap: 6px 14px; flex-wrap: wrap; }
        .ms-cats { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 12px; }
        .ms-cat { padding: 5px 11px; border-radius: 999px; background: var(--accent-soft); color: var(--accent-text); font-size: 12.5px; font-weight: 700; }
        .ms-side { display: flex; flex-direction: column; gap: 8px; min-width: 200px; }
        .ms-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 11px 16px; border-radius: 12px;
            font-weight: 700; font-size: 14px; text-decoration: none; border: 1px solid var(--border); color: var(--text); background: var(--surface);
        }
        .ms-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .ms-about { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 20px 24px; margin-bottom: 22px; }
        .ms-about h2, .ms-works h2 { font-size: 17px; font-weight: 800; margin: 0 0 12px; color: var(--text); }
        .ms-about p { margin: 0; color: var(--text); line-height: 1.65; white-space: pre-line; overflow-wrap: anywhere; }
        .ms-works { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 22px 24px; }
        .ms-empty { text-align: center; padding: 30px 10px; color: var(--text-muted); }
        .ms-note { margin-bottom: 16px; padding: 12px 16px; border-radius: 12px; background: var(--accent-soft); color: var(--accent-text); font-size: 14px; font-weight: 600; }

        @media (max-width: 640px) {
            .ms-page { padding: 16px 0 40px; }
            .ms-head, .ms-about, .ms-works { padding: 16px; border-radius: 14px; }
            .ms-photo, .ms-photo-fallback { width: 64px; height: 64px; font-size: 22px; }
            .ms-side { width: 100%; }
        }
    </style>

    <section class="ms-page">
        <div class="container">

            @if($owner->setting('hide_profile'))
                <div class="ms-note">{{ __('Профиль скрыт в настройках — эту страницу видите только вы.') }}</div>
            @endif

            <div class="ms-head">
                @if($owner->hasAvatar())
                    <img class="ms-photo" src="{{ asset($owner->avatar) }}" alt="{{ $owner->name }}">
                @else
                    <div class="ms-photo-fallback">{{ $owner->initials(2) }}</div>
                @endif

                <div class="ms-main">
                    <h1>{{ $owner->name }}</h1>
                    <div class="ms-sub">
                        <span>🛠 {{ __('Мастер') }}</span>
                        @if($applicant->city)
                            <span>📍 {{ __($applicant->city) }}</span>
                        @endif
                        <span>📸 {{ __('Работ в портфолио: :count', ['count' => $works->count()]) }}</span>
                    </div>
                    @if($categories->isNotEmpty())
                        <div class="ms-cats">
                            @foreach($categories as $category)
                                <span class="ms-cat">{{ $category->title() }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="ms-side">
                    @if(! $hideContacts && $applicant->phone)
                        <a class="ms-btn primary" href="tel:{{ preg_replace('/[^0-9+]/', '', $applicant->phone) }}">📞 {{ $applicant->phone }}</a>
                    @endif
                    @foreach($resumes as $resume)
                        <a class="ms-btn" href="{{ route('public.resumes.show', $resume) }}">📄 {{ __('Резюме') }}: {{ $resume->profession }}</a>
                    @endforeach
                    @if($isOwner)
                        <a class="ms-btn" href="{{ route('applicant.portfolio.index') }}">✏️ {{ __('Изменить портфолио') }}</a>
                    @endif
                </div>
            </div>

            @if($applicant->about_me)
                <div class="ms-about">
                    <h2>{{ __('О себе') }}</h2>
                    <p>{{ $applicant->about_me }}</p>
                </div>
            @endif

            <div class="ms-works">
                <h2>{{ __('Выполненные работы') }}</h2>
                @if($works->isEmpty())
                    <div class="ms-empty">
                        {{ __('Здесь появятся ваши работы. Посторонним страница не видна, пока портфолио пустое.') }}
                        <div style="margin-top:14px;">
                            <a class="ms-btn primary" href="{{ route('applicant.portfolio.create') }}">+ {{ __('Добавить работу') }}</a>
                        </div>
                    </div>
                @else
                    @include('partials.portfolio-gallery', ['works' => $works, 'limit' => null])
                @endif
            </div>
        </div>
    </section>

@endsection
