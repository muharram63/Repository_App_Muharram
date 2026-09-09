{{-- Общий подвал публичной части --}}
<footer>
    <div class="container">
        <div class="footer-row">
            <div class="brand">
                <a href="{{ route('public.home') }}" class="workio-logo"
                   style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                    @include('partials.brand-mark', ['brandSize' => 52])
                    <div style="line-height:1.1;">
                        <div style="font-family: Arial, sans-serif; font-size: 24px; font-weight: 700; color:#111827;">
                            Work<span style="color:#4F46E5;">io</span>
                        </div>
                        <div style="font-family: Arial, sans-serif; font-size: 12px; color:#6B7280;">
                            {{ __('работа без лишних шагов') }}
                        </div>
                    </div>
                </a>
            </div>

            <div class="footer-links">
                <a href="{{ route('public.vacancies.index') }}">{{ __('Вакансии') }}</a>
                <a href="{{ route('public.companies.index') }}">{{ __('Компании') }}</a>
                <a href="{{ route('public.resumes.index') }}">{{ __('Соискатели') }}</a>
                <a href="{{ route('public.home') }}#about">{{ __('О нас') }}</a>
                <a href="{{ route('public.home') }}#companies">{{ __('Компании с вакансиями') }}</a>
            </div>
        </div>
        <div class="footer-copy">© {{ date('Y') }} Workio. {{ __('Все права защищены.') }}</div>
    </div>
</footer>
