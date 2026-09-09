{{--
    Форма назначения онлайн-собеседования (доступна работодателю).
    Использование:
        @include('partials.interview-schedule', [
            'applicantId' => $respApplicant->id,
            'vacancyId' => $vacancy?->id,
            'vacancyResponseId' => $response->id,
        ])
--}}
@auth
    @if(auth()->user()->role === 'employer' && !empty($applicantId))
        <details style="margin-top:12px;">
            <summary style="cursor:pointer; font-size:13px; font-weight:700; color:#4F46E5; list-style:none;">
                {{ __('🎥 Назначить онлайн-собеседование') }}
            </summary>

            <form action="{{ route('public.interviews.store') }}" method="post"
                  style="margin-top:12px; display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                @csrf
                <input type="hidden" name="applicant_id" value="{{ $applicantId }}">
                @if(!empty($vacancyId))
                    <input type="hidden" name="vacancy_id" value="{{ $vacancyId }}">
                @endif
                @if(!empty($vacancyResponseId))
                    <input type="hidden" name="vacancy_response_id" value="{{ $vacancyResponseId }}">
                @endif
                @if(!empty($resumeResponseId))
                    <input type="hidden" name="resume_response_id" value="{{ $resumeResponseId }}">
                @endif

                <label style="font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#8592A6;">
                    {{ __('Дата и время') }}<br>
                    <input type="datetime-local" name="scheduled_at" required
                           value="{{ now()->addDay()->format('Y-m-d\TH:00') }}"
                           style="margin-top:5px; padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px;
                                  font-family:inherit; font-size:13.5px;">
                </label>

                <label style="font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#8592A6;">
                    {{ __('Длительность') }}<br>
                    <select name="duration_minutes"
                            style="margin-top:5px; padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px;
                                   font-family:inherit; font-size:13.5px;">
                        <option value="15">{{ __('15 мин') }}</option>
                        <option value="30" selected>{{ __('30 мин') }}</option>
                        <option value="45">{{ __('45 мин') }}</option>
                        <option value="60">{{ __('60 мин') }}</option>
                        <option value="90">{{ __('90 мин') }}</option>
                    </select>
                </label>

                <label style="flex:1 1 220px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#8592A6;">
                    {{ __('Комментарий') }}<br>
                    <input type="text" name="note" maxlength="500" placeholder="{{ __('Например: техническое интервью') }}"
                           style="margin-top:5px; width:100%; padding:9px 12px; border:1px solid #e2e8f0;
                                  border-radius:10px; font-family:inherit; font-size:13.5px;">
                </label>

                <button type="submit"
                        style="padding:10px 18px; border:none; border-radius:10px; cursor:pointer;
                               font-family:inherit; font-size:13.5px; font-weight:700; background:#4F46E5; color:#fff;">
                    {{ __('Назначить') }}
                </button>
            </form>
        </details>
    @endif
@endauth
