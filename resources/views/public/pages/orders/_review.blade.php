{{--
    Отзыв о мастере на странице заявки.

    Заказчик после «Работа выполнена» видит звёзды и поле для текста; уже
    оставленный отзыв можно исправить, пока не прошло EDIT_DAYS дней.
    Выбранный мастер видит, как его оценили.
--}}
@php
    $review = $order->review;
    $canReview = $order->canBeReviewedBy(auth()->user());
    $chosenName = $order->chosenOffer?->applicant?->user?->name;
@endphp

<style>
    .rv-stars { display: inline-flex; flex-direction: row-reverse; gap: 4px; }
    .rv-stars input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .rv-stars label { font-size: 38px; line-height: 1; cursor: pointer; color: var(--border); transition: color .1s, transform .1s; }
    /* звёзды идут справа налево: :checked ~ label красит выбранную и все «меньшие» */
    .rv-stars input:checked ~ label,
    .rv-stars label:hover, .rv-stars label:hover ~ label { color: #F59E0B; }
    .rv-stars input:focus-visible + label { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 6px; }
    .rv-stars label:active { transform: scale(.9); }
    .rv-hint { font-size: 13px; color: var(--text-muted); min-height: 18px; margin: 4px 0 12px; }
    .rv-form textarea {
        width: 100%; min-height: 96px; padding: 11px 13px; border: 1px solid var(--border); border-radius: 12px;
        background: var(--surface); color: var(--text); font: inherit; font-size: 15px; resize: vertical; margin-bottom: 12px;
    }
    .rv-done { display: flex; flex-direction: column; gap: 6px; }
    .rv-done .rv-big { font-size: 24px; color: #F59E0B; letter-spacing: 2px; }
    .rv-done p { margin: 0; white-space: pre-line; line-height: 1.6; overflow-wrap: anywhere; }
    @media (max-width: 640px) { .rv-form textarea { font-size: 16px; } }
</style>

@if($isOwner && ($canReview || $review))
    <div class="uo-card" id="review">
        @if($review && ! request()->boolean('edit_review'))
            <h2 class="uo-h2">⭐ {{ __('Ваш отзыв о мастере') }}</h2>
            <div class="rv-done">
                <span class="rv-big" aria-label="{{ __('Оценка :n из 5', ['n' => $review->rating]) }}">{{ \App\Models\MasterReview::stars($review->rating) }}</span>
                <b>{{ $review->ratingLabel() }}</b>
                @if($review->comment)<p>{{ $review->comment }}</p>@endif
            </div>
            @if($canReview)
                <a class="uo-btn" style="margin-top:12px;" href="{{ route('public.orders.show', ['order' => $order, 'edit_review' => 1]) }}#review">✏️ {{ __('Изменить отзыв') }}</a>
            @endif
        @else
            <h2 class="uo-h2">⭐ {{ __('Оцените работу мастера') }}</h2>
            <p class="uo-lead" style="margin:0 0 10px;">
                {{ __(':name выполнил вашу заявку. Отзыв поможет другим выбрать мастера, а мастеру — получать больше заказов.', ['name' => $chosenName ?? __('Мастер')]) }}
            </p>
            @if($errors->has('rating') || $errors->has('comment'))
                <div class="uo-flash bad">{{ $errors->first('rating') ?: $errors->first('comment') }}</div>
            @endif
            <form class="rv-form" method="POST" action="{{ route('public.orders.review', $order) }}">
                @csrf
                <div class="rv-stars" role="radiogroup" aria-label="{{ __('Оценка') }}">
                    @foreach(\App\Models\MasterReview::RATINGS as $value => $label)
                        <input type="radio" id="rv{{ $value }}" name="rating" value="{{ $value }}" data-label="{{ __($label) }}"
                               @checked((int) old('rating', $review?->rating) === $value)>
                        <label for="rv{{ $value }}" title="{{ __($label) }}">★</label>
                    @endforeach
                </div>
                <div class="rv-hint" id="rvHint">{{ $review ? $review->ratingLabel() : __('Нажмите на звезду') }}</div>
                <textarea name="comment" maxlength="1000"
                          placeholder="{{ __('Что понравилось или нет: пришёл ли вовремя, как сделал, убрал ли за собой.') }}">{{ old('comment', $review?->comment) }}</textarea>
                <button type="submit" class="uo-btn primary" style="width:100%;">{{ $review ? __('Сохранить отзыв') : __('Оставить отзыв') }}</button>
            </form>
            <script>
                (function () {
                    const hint = document.getElementById('rvHint');
                    document.querySelectorAll('.rv-stars input').forEach(function (input) {
                        input.addEventListener('change', function () { hint.textContent = input.dataset.label; });
                    });
                })();
            </script>
        @endif
    </div>
@elseif($isChosenMaster && $review)
    <div class="uo-card" id="review">
        <h2 class="uo-h2">⭐ {{ __('Отзыв заказчика') }}</h2>
        <div class="rv-done">
            <span class="rv-big">{{ \App\Models\MasterReview::stars($review->rating) }}</span>
            <b>{{ $review->ratingLabel() }}</b>
            @if($review->comment)<p>{{ $review->comment }}</p>@endif
        </div>
    </div>
@endif
