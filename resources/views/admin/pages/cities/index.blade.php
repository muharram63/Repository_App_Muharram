@extends('admin.layouts.app')

@section('main')
    <style>
        .search-wrap {
            margin: 0 3vh 20px;
            max-width: 360px;
            position: relative;
        }
        .search-wrap input[type="text"] {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #334155;
            background: #fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%2394a3b8" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>') no-repeat 12px center;
            transition: border-color 0.2s;
        }
        .search-wrap input[type="text"]:focus {
            outline: none;
            border-color: #1656D6;
            box-shadow: 0 0 0 3px rgba(22, 86, 214, 0.1);
        }
        .search-wrap .clear-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
        }

        #userfilter-all:checked ~ .filters [for="userfilter-all"],
        #userfilter-applicant:checked ~ .filters [for="userfilter-applicant"],
        #userfilter-employer:checked ~ .filters [for="userfilter-employer"] {
            background: #1656D6;
            color: #fff;
            border-color: #1656D6;
            box-shadow: 0 4px 12px rgba(22, 86, 214, 0.15);
        }
        .pill:hover:not(:checked) {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #334155;
        }
    </style>
    {{-- Добавлен только display: block !important, чтобы страница просто показалась --}}
    <section class="page" id="page-users" style="display: block !important; background-color: #fcfcfd;">
        <div class="section-header mb-4">
            <h1 style="color: #1e293b; font-size: 28px;margin-left: 3vh;margin-top: 5vh;">Ваш Регион</h1>
        </div>

        <form action="{{ route('superadmin.cities.index') }}" method="get" class="search-wrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Поиск по стране или городу" onchange="this.form.submit()">
            @if(request('search'))
                <a href="{{ route('superadmin.cities.index') }}" class="clear-btn">✕</a>
            @endif
        </form>
      <a href="{{ route('superadmin.cities.create') }}" class="btn btn-primary" style="margin-left: 63vh;position: relative;bottom: 9.5vh;">
          Добавить
      </a>
        <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; background: #fff;margin-left: 3vh;margin-right: 3vh;position: relative;bottom: 5vh">
            <div class="card-body" style="padding:0;">
                <table class="table table-hover align-middle mb-0" style="vertical-align: middle;">
                    <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">#ID</th>
                        <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Страна</th>
                        <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Регион</th>
                        <th style="padding: 16px 16px; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Действия</th>

                    </tr>
                    </thead>
                    <tbody style="border-top: none;">
                    @foreach($cities as $city)
                        <tr style="border-bottom: 1px solid #f1f5f9;">

                            <td style="color: #64748b;">{{$loop->iteration}}</td>
                            <td style="font-weight: 600; color: #1e293b;">{{$city->country}}</td>
                            <td style="color: #64748b;">{{$city->region}}</td>
                            <td>
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('superadmin.cities.edit',$city) }}" class="btn btn-sm d-inline-flex align-items-center" style="border-radius: 6px; font-weight: 500; background-color: #fef8e6; color: #b45309; border: 1px solid #fde68a; padding: 6px 12px;">
                                        <i class="fa fa-edit me-1"></i> Изменить
                                    </a>
                                    <form action="{{ route('superadmin.cities.destroy',$city) }}" method="post" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm d-inline-flex align-items-center" style="border-radius: 6px; font-weight: 500; background-color: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; padding: 6px 12px;">
                                            <i class="fa fa-trash me-1"></i> Удалить
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

@endsection
