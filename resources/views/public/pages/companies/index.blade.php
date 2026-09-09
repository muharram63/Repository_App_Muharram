@extends('public.layouts.app')


@section('content')
    <link href="{{asset('assets/css.1/bootstrap.css')}}" rel="stylesheet">
    <style>
        .companies-page {
            background: var(--bg);
            min-height: 100vh;
            padding: 48px 0 80px;
        }

        .companies-page .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .companies-hero {
            text-align: center;
            margin-bottom: 36px;
        }

        .companies-hero h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .companies-hero p {
            font-size: 16px;
            color: var(--text-muted);
            margin: 0;
        }

        .companies-controls {
            display: flex;
            flex-direction: column;
            gap: 16px;
            align-items: center;
            margin-bottom: 32px;
        }

        .company-search-wrap {
            position: relative;
            width: 100%;
            max-width: 480px;
        }

        .company-search-wrap svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        #company-search {
            font-family: inherit;
            width: 100%;
            padding: 13px 16px 13px 44px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            color: var(--text);
            background: var(--surface);
            box-shadow: var(--shadow);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        #company-search:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-select {
            font-family: inherit;
            padding: 10px 36px 10px 16px;
            background: var(--surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 12px center;
            border: 1px solid var(--border);
            border-radius: 999px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-muted);
            font-size: 14px;
            appearance: none;
            -webkit-appearance: none;
            outline: none;
            transition: all 0.15s ease-in-out;
        }

        .filter-select:hover {
            background-color: var(--surface-alt);
            border-color: var(--accent);
            color: var(--text);
        }

        .filter-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
        }

        .filter-select.is-active {
            background-color: var(--accent);
            color: #fff;
            border-color: var(--accent);
            box-shadow: var(--shadow);
        }

        .filter-select.is-active option {
            color: var(--text);
            background: var(--surface);
        }

        :root[data-theme="dark"] .company-card-tags .tag { color: var(--accent); }
        :root[data-theme="dark"] .company-avatar-fallback { color: #fff; }

        .companies-count {
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .company-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .company-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--accent);
        }

        .company-card-top {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .company-avatar {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .company-avatar-fallback {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-ink));
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }

        .company-card-title {
            min-width: 0;
        }

        .company-card-title .company-name {
            font-weight: 700;
            color: var(--text);
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .company-card-title .company-contact {
            font-weight: 500;
            color: var(--text-muted);
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .company-card-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .company-card-meta .meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .company-card-meta svg {
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .company-card-tags {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .company-card-tags .tag {
            font-size: 12px;
            font-weight: 600;
            color: var(--accent-ink);
            background: var(--accent-soft);
            padding: 4px 10px;
            border-radius: 999px;
        }

        .company-card-footer {
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .company-card-footer a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px 16px;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            border-radius: 10px;
            text-decoration: none;
            transition: background 0.15s ease;
        }

        .company-card-footer a:hover {
            background: var(--accent-ink);
            color: #fff;
        }

        .companies-empty {
            display: none;
            text-align: center;
            padding: 64px 20px;
            color: var(--text-muted);
            font-size: 15px;
        }

        .companies-pagination {
            margin-top: 40px;
            display: flex;
            justify-content: center;
        }

        @media (max-width: 576px) {
            .companies-hero h1 { font-size: 24px; }
            .companies-grid { grid-template-columns: 1fr; }
        }
    </style>

    <section class="companies-page">
        <div class="container">

            <div class="companies-hero">
                <h1>{{ __('Компании') }}</h1>
                <p>{{ __('Работодатели, зарегистрированные на платформе') }}</p>
            </div>

            <form class="companies-controls" action="{{ route('public.companies.index') }}" method="get">
                <div class="company-search-wrap">
                    <input type="text" name="q" value="{{ $query }}" class="form-control" style="width: 77vh;border-radius: 4vh;"
                           placeholder="{{ __('Поиск по имени, email, компании или стране...') }}" >
                </div>

                <div class="filters">
                    <select name="category" class="filter-select">
                        <option value="">{{ __('Все категории') }}</option>
                        @foreach($categoriesList as $category)
                            <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <select name="industry" class="filter-select" style="width: 25vh">
                        <option value="">{{ __('Все индустрии') }}</option>
                        @foreach($industriesList as $industry)
                            <option value="{{ $industry->id }}" @selected((string) $industryId === (string) $industry->id)>{{ $industry->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="filter-select" style="cursor:pointer; background:#4F46E5; color:#fff; border-color:#4F46E5; font-weight:700;width: 12.7vh;">{{ __('Найти') }}</button>
                </div>
            </form>

            <div class="companies-count" id="companies-count">
                Найдено компаний: {{ method_exists($companies, 'total') ? $companies->total() : count($companies) }}
            </div>

            <div class="companies-grid" id="companies-grid">
                @forelse($companies as $company)
                    <div class="company-card"
                         data-name="{{ mb_strtolower($company->user->name ?? '') }}"
                         data-email="{{ mb_strtolower($company->user->email ?? '') }}"
                         data-company="{{ mb_strtolower($company->company_name ?? '') }}"
                         data-country="{{ mb_strtolower($company->city->country ?? '') }}"
                         data-category-id="{{ $company->category_id }}"
                         data-industry-id="{{ $company->industry_id }}">

                        <div class="company-card-top">
                            @if($company->user?->avatar)
                                <img class="company-avatar" src="{{ asset($company->user->avatar) }}" alt="{{ $company->company_name }}">
                            @else
                                <div class="company-avatar-fallback">
                                    {{ $company->initials(2) }}
                                </div>
                            @endif
                            <div class="company-card-title">
                                <div class="company-name">{{ $company->company_name ?: 'Без названия' }}</div>
                                <div class="company-contact">{{ $company->user?->name ?? '—' }}</div>
                            </div>
                        </div>

                        <div class="company-card-tags">
                            @if($company->category)
                                <span class="tag">{{ $company->category->name }}</span>
                            @endif
                            @if($company->industry)
                                <span class="tag">{{ $company->industry->name }}</span>
                            @endif
                        </div>

                        <div class="company-card-meta">
                            <div class="meta-row">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <span>{{ $company->email_company ?: '—' }}</span>
                            </div>
                            <div class="meta-row">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 21C12 21 19 15.5 19 10C19 6.13 15.87 3 12 3C8.13 3 5 6.13 5 10C5 15.5 12 21 12 21Z" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <span>{{ $company->city ? $company->city->country.', '.$company->city->region : 'Город не указан' }}</span>
                            </div>
                            <div class="meta-row">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="7" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <span>{{ $company->vacancies_count ?? 0 }} вакансий</span>
                            </div>
                        </div>

                        <div class="company-card-footer">
                            <a href="{{ route('public.companies.show', $company) }}">{{ __('Подробнее') }}</a>
                        </div>
                    </div>
                @empty
                    <div style="grid-column:1 / -1; padding:56px 20px; text-align:center;
                                background:var(--surface); border:1px dashed var(--border);
                                border-radius:16px; color:var(--text-muted); font-size:14.5px;">
                        {{ __('Пока нет зарегистрированных компаний') }}
                    </div>
                @endforelse
            </div>

            <div class="companies-empty" id="companies-empty">
                {{ __('Компании не найдены. Попробуйте изменить запрос.') }}
            </div>

            @if(method_exists($companies, 'links'))
                <div class="companies-pagination">
                    {{ $companies->links() }}
                </div>
            @endif

        </div>
    </section>

@endsection
