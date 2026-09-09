@extends('admin.layouts.app')
@section('main')
    <style>
        .cc-wrap{
            --accent:#2563eb;
            --accent-hover:#1d4ed8;
            --border:#e4e7ec;
            --text-main:#101828;
            --text-muted:#667085;
            --radius:10px;
            --shadow:0 1px 2px rgba(16,24,40,.05);

            font-family:"Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif;
            color:var(--text-main);
            max-width:900px;
            width:100%;
            padding:28px 40px 60px;
            box-sizing:border-box;
        }
        .cc-wrap *{ box-sizing:border-box; }

        .cc-breadcrumb{
            font-size:13.5px;
            color:var(--text-muted);
            margin-bottom:18px;
        }
        .cc-breadcrumb a{ color:var(--text-muted); text-decoration:none; }
        .cc-breadcrumb .sep{ margin:0 6px; }
        .cc-breadcrumb .current{ color:var(--text-main); font-weight:500; }

        .cc-topbar{ margin-bottom:28px; }
        .cc-title{
            font-size:28px;
            font-weight:700;
            margin:0 0 6px;
        }
        .cc-subtitle{
            color:var(--text-muted);
            font-size:14.5px;
            margin:0;
        }

        .cc-card{
            background:#fff;
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:32px;
        }
        .cc-field{ margin-bottom:22px; }
        .cc-field:last-of-type{ margin-bottom:0; }
        .cc-wrap label{
            display:block;
            font-size:13.5px;
            font-weight:600;
            color:var(--text-main);
            margin-bottom:7px;
        }
        .cc-hint{
            font-size:12.5px;
            color:var(--text-muted);
            margin-top:6px;
        }
        .cc-wrap input[type="text"],
        .cc-wrap textarea,
        .cc-wrap select{
            width:100%;
            font-size:14.5px;
            font-family:inherit;
            padding:10px 13px;
            border:1px solid var(--border);
            border-radius:8px;
            background:#fff;
            color:var(--text-main);
            outline:none;
            transition:border-color .15s, box-shadow .15s;
        }
        .cc-wrap textarea{ resize:vertical; min-height:88px; }
        .cc-wrap input[type="text"]:focus,
        .cc-wrap textarea:focus,
        .cc-wrap select:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 3px rgba(37,99,235,.12);
        }
        .cc-row-2{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:20px;
        }
        .cc-icon-picker{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .cc-icon-option{
            width:44px;height:44px;
            border:1px solid var(--border);
            border-radius:9px;
            display:flex;align-items:center;justify-content:center;
            font-size:18px;
            cursor:pointer;
            background:#fff;
            transition:border-color .15s, background .15s;
        }
        .cc-icon-option.selected{
            border-color:var(--accent);
            background:#eef3ff;
        }
        .cc-switch-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            border:1px solid var(--border);
            border-radius:9px;
            padding:14px 16px;
        }
        .cc-switch-text .cc-title-sm{ font-size:14px; font-weight:600; }
        .cc-switch-text .cc-desc{ font-size:12.5px; color:var(--text-muted); margin-top:2px; }
        .cc-switch{
            position:relative;
            width:42px;height:24px;
            flex-shrink:0;
        }
        .cc-switch input{ opacity:0; width:0; height:0; }
        .cc-switch .cc-slider{
            position:absolute; inset:0;
            background:#d0d5dd;
            border-radius:999px;
            cursor:pointer;
            transition:.15s;
        }
        .cc-switch .cc-slider:before{
            content:"";
            position:absolute;
            width:18px;height:18px;
            left:3px;top:3px;
            background:#fff;
            border-radius:50%;
            transition:.15s;
        }
        .cc-switch input:checked + .cc-slider{ background:var(--accent); }
        .cc-switch input:checked + .cc-slider:before{ transform:translateX(18px); }

        .cc-actions{
            display:flex;
            justify-content:flex-end;
            gap:12px;
            margin-top:30px;
            padding-top:22px;
            border-top:1px solid var(--border);
        }
        .cc-btn{
            font-size:14.5px;
            font-weight:600;
            padding:10px 20px;
            border-radius:8px;
            border:1px solid transparent;
            cursor:pointer;
        }
        .cc-btn-ghost{
            background:#fff;
            border-color:var(--border);
            color:var(--text-main);
        }
        .cc-btn-ghost:hover{ background:#f7f8fa; }
        .cc-btn-primary{
            background:var(--accent);
            color:#fff;
        }
        .cc-btn-primary:hover{ background:var(--accent-hover); }

        @media (max-width: 640px){
            .cc-row-2{ grid-template-columns:1fr; }
            .cc-wrap{ padding:20px; }
        }
    </style>

    <div class="cc-wrap">

        <div class="cc-breadcrumb">
            <a href="#">Админ-панель</a>
            <span class="sep">›</span>
            <a href="{{route('superadmin.cities.index')}}">Страна && Регион</a>
            <span class="sep">›</span>
            <span class="current">Добавить</span>
        </div>

        <div class="cc-topbar">
            <h1 class="cc-title">Добавить Страну</h1>
        </div>

        <form action="{{route('superadmin.cities.store')}}" method="post" class="cc-card" >
            @csrf
            <div class="cc-field">
                <label for="cc-name">Страна</label>
                <input type="text" id="cc-name" placeholder="Например: Таджикистан" name="country" value="{{old('country')}}">
                <div class="cc-hint">Отображается пользователям в списке Соискателей и Работаделей</div>
            </div>
            <div class="cc-field">
                <label for="cc-name"></label>
                <input type="text" id="cc-name" placeholder="Например: Согдийская область,город Худжанд" name="region" value="{{old('region')}}">
            </div>

            <div class="cc-actions">
                <a href="{{route('superadmin.cities.index')}}" class="btn btn-outline-danger">Отмена</a>
                <button type="submit" class="cc-btn cc-btn-primary">Добавить</button>
            </div>
        </form>

    </div>


@endsection
