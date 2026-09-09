{{-- Фирменный знак Workio. Один источник для всех страниц — та же картинка,
     что стоит в шапке главной. Размер задаётся параметром brandSize. --}}
@php($brandSize = $brandSize ?? 50)
<img src="{{ asset('assets/img/work1.jfif') }}"
     width="{{ $brandSize }}" height="{{ $brandSize }}"
     alt="Workio"
     style="border-radius:2vh; object-fit:cover; flex-shrink:0; display:block;">
