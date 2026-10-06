@extends('public.layouts.app')
@section('content')

    <style>
        .uo-offer { padding: 16px 0; border-top: 1px solid var(--border); }
        .uo-offer:first-of-type { border-top: 0; padding-top: 4px; }
        .uo-offer-head { display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
        .uo-offer-ava {
            width: 48px; height: 48px; border-radius: 50%; flex: none; object-fit: cover;
            background: var(--accent-soft); color: var(--accent-text); display: flex; align-items: center; justify-content: center; font-weight: 800;
        }
        .uo-offer-name { font-weight: 800; font-size: 15.5px; }
        .uo-offer-name a { color: var(--text); text-decoration: none; }
        .uo-offer-name a:hover { color: var(--accent); }
        .uo-offer-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 13px; color: var(--text-muted); margin-top: 3px; align-items: center; }
        .uo-offer-price { text-align: right; }
        .uo-offer-price b { display: block; font-size: 16px; }
        .uo-offer-price small { color: var(--text-muted); font-size: 12.5px; }
        .uo-offer-msg { margin: 10px 0; white-space: pre-line; line-height: 1.6; overflow-wrap: anywhere; }
        .uo-offer-works { margin-top: 10px; }
        .uo-choose { margin: 4px 0 6px; }
        .uo-chosen { border: 2px solid var(--accent); }
        .uo-call { width: 100%; margin-top: 12px; font-size: 16px; padding: 14px; }
        .uo-facts a { color: var(--accent); }
        .uo-reassign { margin-top: 12px; border: 1px dashed var(--border); border-radius: 12px; padding: 10px 14px; }
        .uo-reassign summary { cursor: pointer; font-weight: 600; font-size: 14px; color: var(--text-muted); }
        .uo-reassign form { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
        .uo-reassign label { font-size: 13px; font-weight: 700; }
        .uo-reassign select {
            padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface);
            color: var(--text); font: inherit; font-size: 15px;
        }
        .uo-offer-form label { display: block; font-size: 14px; font-weight: 700; margin-bottom: 6px; }
        .uo-offer-form textarea, .uo-offer-form input, .uo-offer-form select {
            width: 100%; padding: 11px 13px; border: 1px solid var(--border); border-radius: 12px;
            background: var(--surface); color: var(--text); font: inherit; font-size: 15px; margin-bottom: 14px;
        }
        .uo-offer-form input[type=number] { max-width: 180px; margin-bottom: 0; }
        .uo-offer-form textarea { min-height: 100px; resize: vertical; }
        .uo-offer-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; margin-bottom: 14px; }
        .uo-offer-row select { margin-bottom: 0; }
        @media (max-width: 640px) {
            .uo-offer-price { text-align: left; width: 100%; }
            .uo-offer-row { grid-template-columns: 1fr; gap: 14px; }
            .uo-offer-form textarea, .uo-offer-form input, .uo-offer-form select { font-size: 16px; }
        }
        .uo-head { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .uo-facts { display: grid; grid-template-columns: max-content 1fr; gap: 10px 18px; font-size: 14.5px; margin: 0; }
        .uo-facts dt { color: var(--text-muted); }
        .uo-facts dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
        .uo-desc { white-space: pre-line; line-height: 1.65; overflow-wrap: anywhere; margin: 0; }
        .uo-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
        .uo-gallery a { display: block; aspect-ratio: 4 / 3; border-radius: 12px; overflow: hidden; background: var(--surface-alt, #F1F3F8); }
        .uo-gallery img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .uo-h2 { font-size: 16px; font-weight: 800; margin: 0 0 12px; }
        .uo-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .uo-actions form { margin: 0; }
        @media (max-width: 640px) {
            .uo-facts { grid-template-columns: 1fr; gap: 2px; }
            .uo-facts dd { margin-bottom: 10px; }
            .uo-actions .uo-btn { width: 100%; }
            .uo-actions form { width: 100%; }
        }
    </style>

    <section class="uo-page">
        <div class="container">
            @if($isOwner)
                <a class="uo-back" href="{{ route('public.orders.mine') }}">← {{ __('Мои заявки') }}</a>
            @else
                <a class="uo-back" href="{{ route('public.orders.index') }}">← {{ __('Срочные заказы') }}</a>
            @endif

            @include('public.pages.orders._styles')

            <div class="uo-head">
                @if($order->isOpen() && $order->isUrgent())
                    <span class="uo-badge urgent">🔥 {{ __('Срочно') }}</span>
                @endif
                <span class="uo-badge {{ $order->isOpen() ? 'status' : 'closed' }}">{{ $order->statusLabel() }}</span>
            </div>
            <h1 class="uo-h1">{{ $order->title }}</h1>
            <p class="uo-lead">
                {{ __('Опубликована :date', ['date' => $order->created_at->format('d.m.Y H:i')]) }}
            </p>

            <div class="uo-card">
                <dl class="uo-facts">
                    <dt>{{ __('Мастер') }}</dt>
                    <dd>{{ $order->category?->title() ?? '—' }}</dd>

                    <dt>{{ __('Срок') }}</dt>
                    <dd>
                        {{ $order->deadlineLabel() }} · {{ __('до :date', ['date' => $order->deadline_at->format('d.m.Y H:i')]) }}
                        @if($order->timeLeftLabel())
                            <span style="color:var(--text-muted); font-weight:500;">({{ __('осталось :left', ['left' => $order->timeLeftLabel()]) }})</span>
                        @endif
                    </dd>

                    <dt>{{ __('Где') }}</dt>
                    <dd>{{ $order->place() ?: '—' }}</dd>

                    @if($showAddress)
                        <dt>{{ __('Адрес') }}</dt>
                        <dd>
                            {{ $order->address ?: '—' }}
                            @if($order->address)
                                <div style="font-weight:500; font-size:12.5px; color:var(--text-muted); margin-top:3px;">
                                    🔒 {{ __('Виден только вам и мастеру, которого вы выберете.') }}
                                </div>
                            @endif
                        </dd>
                    @endif

                    @if($showAddress && $order->contactPhone())
                        <dt>{{ __('Телефон заказчика') }}</dt>
                        <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->contactPhone()) }}">{{ $order->contactPhone() }}</a></dd>
                    @endif

                    <dt>{{ __('Бюджет') }}</dt>
                    <dd>{{ $order->budget ? number_format($order->budget, 0, ',', ' ').' '.__('сомони') : __('по договорённости') }}</dd>
                </dl>
            </div>

            <div class="uo-card">
                <h2 class="uo-h2">{{ __('Что нужно сделать') }}</h2>
                <p class="uo-desc">{{ $order->description }}</p>
            </div>

            @if($order->photos->isNotEmpty())
                <div class="uo-card">
                    <h2 class="uo-h2">{{ __('Фото') }} · {{ $order->photos->count() }}</h2>
                    <div class="uo-gallery">
                        @foreach($order->photos as $photo)
                            <a href="{{ $photo->url() }}" target="_blank" rel="noopener">
                                <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Фото :n', ['n' => $loop->iteration]) }}" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @php
                $chosen = $order->chosenOffer;
                $chosenMaster = $chosen?->applicant;
            @endphp
            @if($isOwner && $chosen && in_array($order->status, ['assigned', 'done'], true))
                <div class="uo-card uo-chosen">
                    <h2 class="uo-h2">{{ $order->status === 'done' ? '✅ '.__('Работу выполнил') : '🤝 '.__('Исполнитель') }}</h2>
                    <div class="uo-offer-head">
                        <span class="uo-offer-ava">{{ $chosenMaster?->user?->initials(2) ?? '?' }}</span>
                        <div style="flex:1; min-width:0;">
                            <div class="uo-offer-name">
                                @if($chosenMaster)
                                    <a href="{{ route('public.masters.show', $chosenMaster) }}">{{ $chosenMaster->user?->name }}</a>
                                @endif
                            </div>
                            <div class="uo-offer-meta">
                                <span>{{ $chosen->price ? number_format($chosen->price, 0, ',', ' ').' '.__('сомони') : __('цена после осмотра') }}</span>
                                <span>⏱ {{ $chosen->canStartLabel() }}</span>
                            </div>
                        </div>
                    </div>
                    @if($chosenMaster?->phone)
                        <a class="uo-btn primary uo-call" href="tel:{{ preg_replace('/[^0-9+]/', '', $chosenMaster->phone) }}">📞 {{ $chosenMaster->phone }}</a>
                    @else
                        <p class="uo-lead" style="margin:10px 0 0;">{{ __('Мастер не указал телефон в анкете — он позвонит вам сам: ему открыт ваш номер.') }}</p>
                    @endif
                    @if($order->isAssigned())
                        {{-- мастер не пришёл — выбрать другого, с новым сроком --}}
                        <details class="uo-reassign">
                            <summary>{{ __('Мастер не пришёл или не отвечает?') }}</summary>
                            <form method="POST" action="{{ route('public.orders.reassign', $order) }}"
                                  data-confirm="{{ __('Отменить выбор этого мастера? Он потеряет доступ к адресу и телефону, а заявка снова откроется.') }}"
                                  onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('PATCH')
                                <label for="raDeadline">{{ __('Новый срок') }}</label>
                                <select id="raDeadline" name="deadline_option">
                                    @foreach(\App\Models\UrgentOrder::DEADLINES as $value => $label)
                                        <option value="{{ $value }}" @selected($value === 'today')>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="uo-btn" style="width:100%;">↻ {{ __('Выбрать другого мастера') }}</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('public.orders.complete', $order) }}" style="margin-top:12px;"
                              data-confirm="{{ __('Мастер выполнил работу? Заявка закроется.') }}" onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="uo-btn" style="width:100%;">✅ {{ __('Работа выполнена') }}</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- выбранный мастер: адрес, телефон заказчика --}}
            @if($isChosenMaster)
                <div class="uo-card uo-chosen">
                    <h2 class="uo-h2">🎉 {{ $order->status === 'done' ? __('Заказ выполнен — спасибо!') : __('Вас выбрали исполнителем') }}</h2>
                    @if($order->isAssigned())
                        <p class="uo-lead" style="margin:0 0 12px;">{{ __('Свяжитесь с заказчиком и договоритесь о времени. Адрес и телефон — ниже, их видите только вы.') }}</p>
                    @endif
                    <dl class="uo-facts">
                        <dt>{{ __('Заказчик') }}</dt>
                        <dd>{{ $order->user?->name }}</dd>
                        <dt>{{ __('Адрес') }}</dt>
                        <dd>{{ collect([$order->place(), $order->address])->filter()->implode(', ') ?: '—' }}</dd>
                    </dl>
                    @if($order->contactPhone() && $order->isAssigned())
                        <a class="uo-btn primary uo-call" href="tel:{{ preg_replace('/[^0-9+]/', '', $order->contactPhone()) }}">📞 {{ $order->contactPhone() }}</a>
                    @endif
                </div>
            @endif

            @if($isOwner || $offers->isNotEmpty())
                <div class="uo-card" id="offers">
                    <h2 class="uo-h2">{{ __('Отклики мастеров') }} · {{ $offers->count() }}</h2>

                    @forelse($offers as $offer)
                        @php
                            $master = $offer->applicant;
                            $masterUser = $master?->user;
                            $works = $master
                                ? $master->portfolioWorks->where('work_category_id', $order->work_category_id)->values()
                                : collect();
                            // работ нужного вида нет — покажем любые: лучше, чем ничего
                            $works = $works->isNotEmpty() ? $works : ($master?->portfolioWorks ?? collect());
                        @endphp
                        <article class="uo-offer">
                            <div class="uo-offer-head">
                                @if($masterUser?->hasAvatar())
                                    <img class="uo-offer-ava" src="{{ asset($masterUser->avatar) }}" alt="">
                                @else
                                    <span class="uo-offer-ava">{{ $masterUser?->initials(2) ?? '?' }}</span>
                                @endif
                                <div style="flex:1; min-width:0;">
                                    <div class="uo-offer-name">
                                        @if($master)
                                            <a href="{{ route('public.masters.show', $master) }}">{{ $masterUser?->name }}</a>
                                        @else
                                            {{ __('Мастер удалён') }}
                                        @endif
                                    </div>
                                    <div class="uo-offer-meta">
                                        @if($master?->city)<span>📍 {{ __($master->city) }}</span>@endif
                                        <span>📸 {{ __('Работ в портфолио: :count', ['count' => $master?->portfolioWorks->count() ?? 0]) }}</span>
                                        <span>{{ $offer->created_at->format('d.m H:i') }}</span>
                                    </div>
                                </div>
                                <div class="uo-offer-price">
                                    <b>{{ $offer->price ? number_format($offer->price, 0, ',', ' ').' '.__('сомони') : __('цена после осмотра') }}</b>
                                    <small>⏱ {{ $offer->canStartLabel() }}</small>
                                </div>
                            </div>
                            <p class="uo-offer-msg">{{ $offer->message }}</p>
                            @if($offer->status !== \App\Models\UrgentOrderOffer::STATUS_PENDING)
                                <span class="uo-badge {{ $offer->status === 'chosen' ? 'done' : 'closed' }}">{{ $offer->customerStatusLabel() }}</span>
                            @elseif($isOwner && $order->isOpen())
                                <form method="POST" action="{{ route('public.orders.choose', [$order, $offer]) }}" class="uo-choose"
                                      data-confirm="{{ __('Выбрать :name исполнителем? Ему откроются адрес и ваш телефон, остальным мастерам придёт отказ.', ['name' => $masterUser?->name]) }}"
                                      onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf
                                    <button type="submit" class="uo-btn primary">✓ {{ __('Выбрать исполнителем') }}</button>
                                </form>
                            @endif
                            @if($works->isNotEmpty())
                                <div class="uo-offer-works">
                                    @include('partials.portfolio-gallery', ['works' => $works, 'limit' => 3])
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="uo-lead" style="margin:0;">
                            @if($order->isOpen())
                                {{ __('Пока откликов нет. Мастера подходящего профиля увидят заявку и предложат цену — мы пришлём уведомление.') }}
                            @else
                                {{ __('Заявка закрыта.') }}
                            @endif
                        </p>
                    @endforelse
                </div>
            @endif

            {{-- мастер: свой отклик или форма отклика --}}
            @if(! $isOwner)
                @if($myOffer && $myOffer->status !== \App\Models\UrgentOrderOffer::STATUS_WITHDRAWN)
                    <div class="uo-card">
                        <h2 class="uo-h2">{{ __('Ваш отклик') }}</h2>
                        <div class="uo-offer-meta" style="margin-bottom:8px;">
                            <span class="uo-badge status">{{ $myOffer->statusLabel() }}</span>
                            <span>{{ $myOffer->price ? number_format($myOffer->price, 0, ',', ' ').' '.__('сомони') : __('цена после осмотра') }}</span>
                            <span>⏱ {{ $myOffer->canStartLabel() }}</span>
                        </div>
                        <p class="uo-offer-msg">{{ $myOffer->message }}</p>
                        @if($myOffer->status === \App\Models\UrgentOrderOffer::STATUS_PENDING && $order->isOpen())
                            <form method="POST" action="{{ route('public.orders.offers.withdraw', $order) }}"
                                  data-confirm="{{ __('Отозвать отклик?') }}" onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="uo-btn danger">{{ __('Отозвать отклик') }}</button>
                            </form>
                        @endif
                    </div>
                @elseif($cannotOffer === null)
                    <div class="uo-card" id="offer-form">
                        <h2 class="uo-h2">{{ __('Откликнуться') }}</h2>
                        @if($errors->any())
                            <div class="uo-flash bad"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif
                        <form class="uo-offer-form" method="POST" action="{{ route('public.orders.offers.store', $order) }}">
                            @csrf
                            <label for="ofMessage">{{ __('Что предлагаете') }}</label>
                            <textarea id="ofMessage" name="message" maxlength="1000" required
                                      placeholder="{{ __('Например: Посмотрю насос и фильтр, с собой запчасти для Samsung. Опыт 7 лет.') }}">{{ old('message') }}</textarea>

                            <div class="uo-offer-row">
                                <div>
                                    <label for="ofPrice">{{ __('Цена') }} <span style="font-weight:500; color:var(--text-muted)">({{ __('необязательно') }})</span></label>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <input id="ofPrice" type="number" name="price" min="1" max="10000000" inputmode="numeric" value="{{ old('price') }}" placeholder="250">
                                        <span style="font-weight:600;">{{ __('сомони') }}</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="ofStart">{{ __('Когда сможете приступить') }}</label>
                                    <select id="ofStart" name="can_start" required>
                                        @foreach($canStart as $value => $label)
                                            <option value="{{ $value }}" @selected(old('can_start', 'today') === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <p class="uo-lead" style="font-size:13px; margin:0 0 12px;">📸 {{ __('Заказчик увидит ваше портфолио рядом с откликом.') }}</p>
                            <button type="submit" class="uo-btn primary" style="width:100%;">{{ __('Отправить отклик') }}</button>
                        </form>
                    </div>
                @else
                    <div class="uo-card" style="font-size:14px;">
                        {{ $cannotOffer }}
                        @guest
                            <div style="margin-top:12px;"><a class="uo-btn primary" href="{{ route('login') }}">{{ __('Войти') }}</a></div>
                        @endguest
                        @auth
                            @if(auth()->user()->applicant && ! auth()->user()->applicant->portfolioWorks()->exists())
                                <div style="margin-top:12px;"><a class="uo-btn primary" href="{{ route('applicant.portfolio.create') }}">+ {{ __('Добавить работу') }}</a></div>
                            @endif
                        @endauth
                    </div>
                @endif
            @endif

            @if($isOwner)

                @if(in_array($order->status, ['expired', 'cancelled'], true))
                    <div class="uo-card" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <span style="flex:1; min-width:200px; font-size:14px;">
                            {{ $order->status === 'expired'
                                ? __('Срок истёк, исполнитель не выбран. Повторите заявку — данные подставятся сами.')
                                : __('Заявка отменена. Если мастер всё ещё нужен — повторите её.') }}
                        </span>
                        <a class="uo-btn primary" href="{{ route('public.orders.create', ['repeat' => $order->id]) }}">↻ {{ __('Повторить заявку') }}</a>
                    </div>
                @endif

                @if($order->isOpen() || $order->isAssigned())
                    <div class="uo-actions">
                        <form method="POST" action="{{ route('public.orders.cancel', $order) }}"
                              data-confirm="{{ $order->isAssigned() ? __('Отменить заявку? Исполнителю придёт уведомление.') : __('Отменить заявку? Мастера перестанут её видеть.') }}"
                              onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="uo-btn danger">{{ __('Отменить заявку') }}</button>
                        </form>
                    </div>
                @endif
            @endif
        </div>
    </section>

@endsection
