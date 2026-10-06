@extends('public.layouts.app')
@section('content')

    <section class="uo-page">
        <div class="container">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:18px;">
                <div>
                    <h1 class="uo-h1">{{ __('Мои заявки') }}</h1>
                    <p class="uo-lead" style="margin:0;">{{ __('Срочные заявки на мастера: что нужно сделать, к какому сроку и кто откликнулся.') }}</p>
                </div>
                @if($canCreate)
                    <a class="uo-btn primary" href="{{ route('public.orders.create') }}">+ {{ __('Нужен мастер') }}</a>
                @endif
            </div>

            @include('public.pages.orders._styles')

            @forelse($orders as $order)
                @include('public.pages.orders._card', ['order' => $order, 'offersCount' => $order->active_offers_count])
            @empty
                <div class="uo-card" style="text-align:center; padding:40px 18px;">
                    <div style="font-size:42px; margin-bottom:8px;">🛠</div>
                    <div style="font-weight:800; font-size:17px; margin-bottom:6px;">{{ __('Заявок пока нет') }}</div>
                    <p class="uo-lead" style="max-width:460px; margin:0 auto 16px;">
                        {{ __('Сломалась техника, протекает кран, нужно покрасить стены? Создайте заявку — мастера откликнутся, а вы выберете по их работам.') }}
                    </p>
                    <a class="uo-btn primary" href="{{ route('public.orders.create') }}">+ {{ __('Нужен мастер') }}</a>
                </div>
            @endforelse

            @if($orders->hasPages())
                <div style="margin-top:16px;">{{ $orders->links() }}</div>
            @endif
        </div>
    </section>

@endsection
