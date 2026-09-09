@extends('public.layouts.app')


@section('content')

    <style>
        .company-page {
            background: #f8fafc;
            min-height: 100vh;
            padding: 48px 0 80px;
        }

        .company-page .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .company-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 24px;
            transition: color 0.15s ease;
        }

        .company-back:hover {
            color: #1656D6;
        }

        .company-header {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .company-header-avatar {
            width: 96px;
            height: 96px;
            border-radius: 20px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .company-header-avatar-fallback {
            width: 96px;
            height: 96px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1656D6, #3b82f6);
            color: #fff;
            font-weight: 700;
            font-size: 32px;
            flex-shrink: 0;
        }

        .company-header-info {
            flex: 1;
            min-width: 200px;
        }

        .company-header-info h1 {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 6px;
        }

        .company-header-info .company-job {
            font-size: 15px;
            color: #64748b;
            margin: 0 0 12px;
        }

        .company-header-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .company-header-tags .tag {
            font-size: 13px;
            font-weight: 600;
            color: #1656D6;
            background: rgba(22, 86, 214, 0.08);
            padding: 5px 12px;
            border-radius: 999px;
        }

        .company-header-cta {
            flex-shrink: 0;
        }

        .company-header-cta a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background: #1656D6;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            border-radius: 10px;
            text-decoration: none;
            transition: background 0.15s ease;
            white-space: nowrap;
        }

        .company-header-cta a:hover {
            background: #1046b0;
            color: #fff;
        }

        .company-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .company-card-block {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
        }

        .company-card-block h2 {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 16px;
        }

        .company-description {
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
            white-space: pre-line;
            margin: 0;
        }

        .company-details-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .company-details-list .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .company-details-list svg {
            color: #1656D6;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .company-details-list .detail-label {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
        }

        .company-details-list .detail-value {
            font-size: 14px;
            color: #334155;
            font-weight: 500;
            word-break: break-word;
        }

        .company-details-list .detail-value a {
            color: #1656D6;
            text-decoration: none;
        }

        .company-details-list .detail-value a:hover {
            text-decoration: underline;
        }

        @media (max-width: 720px) {
            .company-grid {
                grid-template-columns: 1fr;
            }

            .company-header {
                flex-direction: column;
                text-align: center;
            }

            .company-header-tags {
                justify-content: center;
            }

            .company-header-cta {
                width: 100%;
            }

            .company-header-cta a {
                width: 100%;
            }
        }
    </style>

    <section class="company-page">
        <div class="container">

            <a href="{{ route('public.companies.index') }}" class="company-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                {{ __('Все компании') }}
            </a>

            <div class="company-header">
                @if($company->user?->hasAvatar())
                    <img class="company-header-avatar" src="{{ asset($company->user->avatar) }}" alt="{{ $company->company_name }}">
                @else
                    <div class="company-header-avatar-fallback">
                        {{ mb_substr($company->user->name, 0, 1) }}
                    </div>
                @endif

                <div class="company-header-info">
                    <h1>{{ $company->company_name }}</h1>
                    <p class="company-job">{{ $company->job }} · {{ $company->user->name }}</p>
                    <div class="company-header-tags">
                        @if($company->category)
                            <span class="tag">{{ $company->category->name }}</span>
                        @endif
                        @if($company->industry)
                            <span class="tag">{{ $company->industry->name }}</span>
                        @endif
                    </div>
                </div>

                @if($company->website_url)
                    <div class="company-header-cta">
                        <a href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">{{ __('Сайт компании') }}</a>
                    </div>
                @endif
            </div>

            <div class="company-grid">
                <div class="company-card-block">
                    <h2>{{ __('О компании') }}</h2>
                    <p class="company-description">{{ $company->description }}</p>
                </div>

                <div class="company-card-block">
                    @include('partials.complaint-form', ['targetType' => 'company', 'targetId' => $company->id])

                    <h2>{{ __('Контактная информация') }}</h2>
                    <div class="company-details-list">

                        <div class="detail-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <div>
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><a href="mailto:{{ $company->email_company }}">{{ $company->email_company }}</a></div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 16.92V19.92C22 20.4704 21.5523 20.92 21 20.92C10.5066 20.92 2 12.4134 2 1.92C2 1.36772 2.44772 0.92 3 0.92H6C6.55228 0.92 7 1.36772 7 1.92C7 3.42 7.24 4.87 7.69 6.22C7.82 6.61 7.72 7.04 7.42 7.34L5.94 8.82C7.28 11.66 9.34 13.72 12.18 15.06L13.66 13.58C13.96 13.28 14.39 13.18 14.78 13.31C16.13 13.76 17.58 14 19.08 14C19.6323 14 20.08 14.4477 20.08 15V16.92" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div>
                                <div class="detail-label">{{ __('Телефон') }}</div>
                                <div class="detail-value"><a href="tel:{{ $company->phone }}">{{ $company->phone }}</a></div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 21C12 21 19 15.5 19 10C19 6.13 15.87 3 12 3C8.13 3 5 6.13 5 10C5 15.5 12 21 12 21Z" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <div>
                                <div class="detail-label">{{ __('Местоположение') }}</div>
                                <div class="detail-value">{{ $company->city->country }}, {{ $company->city->region }}, г. {{ $company->city->name ?? $company->city->city }}</div>
                            </div>
                        </div>

                        @if($company->website_url)
                            <div class="detail-row">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M3 12H21" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M12 3C14.5 5.5 15.8 8.68 15.8 12C15.8 15.32 14.5 18.5 12 21C9.5 18.5 8.2 15.32 8.2 12C8.2 8.68 9.5 5.5 12 3Z" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <div>
                                    <div class="detail-label">{{ __('Сайт') }}</div>
                                    <div class="detail-value"><a href="{{ $company->website_url }}" target="_blank" rel="noopener noreferrer">{{ $company->website_url }}</a></div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection
