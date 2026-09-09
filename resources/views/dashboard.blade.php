@extends('public.layouts.app')


@section('content')
    <link href="{{asset('assets/css.1/bootstrap.css')}}" rel="stylesheet">
    <style>

        .find {
            position: relative;
            width: 207vh;
            margin-left: 4vh;
            margin-top: 7vh;
            border-radius: 7vh;
            height: 53vh;
            text-align: center;
            padding-top: 13vh;
            border: 2px solid blue;
        }



        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    .find-input{
        display: grid;
        gap: 2vh;
    }
    #typewriter::after {
        content: '|';
        animation: blink 0.7s infinite;

    }


    h1{
        font-family: 'Montserrat' , sans-serif;
    }
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }
</style>

<div class="hero">
   <div style="text-align: center;height: 63vh;">
        <h1 style="padding-top: 7vh">Найти <span id="typewriter"></span><br> в Таджикистане</h1>
        <h2 style="color: darkgrey">Находите сотрудников среди тех, кто хочет у вас работать.</h2>

        @auth
            @if(auth()->user()->role === 'employer')
                <a href="{{ route('employer.vacancies.create') }}" class="btn btn-primary">
                    Разместить Вакансию
                </a>
            @else
                {{-- обычный пользователь, не employer --}}
                <a href="{{ route('public.home') }}" title="Доступно только для работодателей" class="btn btn-primary">
                    Разместить Вакансию
                </a>
            @endif
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">
                Разместить Вакансию
            </a>
        @endauth
    </div>

    <div class="find">
        <h1>Мы поможем вам найти кандидатов</h1>
       <form action="" >
           @csrf
           <div style="display: flex;justify-content: center;gap: 2vh;">

            <div class="find-input">
                <label></label>
               <input type="text" name="profession" placeholder="Профессия" class="form-control" style="width: 50vh;height: 8vh;">
            </div>

               <div class="find-input">
                   <label></label>
                   <input type="text" name="city" placeholder="Город или страна (Локация)" class="form-control" style="width: 50vh;height: 8vh;">
               </div>
               <div class="find-input">
                   <label></label>
                   <input type="submit"  class="form-control btn btn-primary" style="width: 33vh;height: 8vh;">
               </div>
               <h2></h2>
            </div>

            </form>
    </div>
</div>






<script>
    const phrases = [
        "курьера",
        "водителя",
        "менеджера",
        "разработчика"
    ];

    const el = document.getElementById('typewriter');
    let phraseIndex = 0;
    let charIndex = 0;
    let isDeleting = false;

    function type() {
        const currentPhrase = phrases[phraseIndex];

        if (isDeleting) {
            el.textContent = currentPhrase.substring(0, charIndex - 1);
            charIndex--;
        } else {
            el.textContent = currentPhrase.substring(0, charIndex + 1);
            charIndex++;
        }

        let typeSpeed = isDeleting ? 50 : 100;

        if (!isDeleting && charIndex === currentPhrase.length) {
            typeSpeed = 1500; // пауза перед удалением
            isDeleting = true;
        } else if (isDeleting && charIndex === 0) {
            isDeleting = false;
            phraseIndex = (phraseIndex + 1) % phrases.length;
            typeSpeed = 300; // пауза перед новой фразой
        }

        setTimeout(type, typeSpeed);
    }

    type();
</script>




@endsection
