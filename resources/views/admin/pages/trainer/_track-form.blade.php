<form method="POST" action="{{ $action }}" style="display:grid; gap:8px; margin-top:10px; max-width:720px">
    @csrf
    <label class="tn-muted">Название
        <input class="tn-in" name="title" value="{{ $track?->title }}" required maxlength="120" style="width:100%" placeholder="Например: PHP-разработчик">
    </label>
    <label class="tn-muted">Описание — о чём тренировка (видит ИИ, когда составляет вопросы и задание)
        <textarea class="tn-in" name="description" rows="2" maxlength="2000" style="width:100%">{{ $track?->description }}</textarea>
    </label>
    <label class="tn-muted">Навыки — по одному в строке: «Навык; обязательный или желательный; вес»
        <textarea class="tn-in" name="skills" rows="5" required maxlength="4000" style="width:100%; font-family:monospace"
                  placeholder="PHP и Laravel; обязательный; 40&#10;SQL и базы данных; обязательный; 30&#10;REST API; желательный; 20">{{ $track ? \App\Http\Controllers\Admin\AdminTrainerController::skillsText($track->skills ?? []) : '' }}</textarea>
    </label>
    <div class="tn-row">
        <label class="tn-muted">Язык
            <select class="tn-in" name="language">
                @foreach(['ru' => 'Русский', 'tg' => 'Таджикский', 'en' => 'Английский'] as $code => $name)
                    <option value="{{ $code }}" @selected(($track?->language ?? 'ru') === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="tn-muted">Порядок
            <input class="tn-in" type="number" name="position" value="{{ $track?->position ?? 0 }}" min="0" style="width:80px">
        </label>
        <label class="tn-muted"><input type="checkbox" name="active" value="1" @checked($track?->active ?? true)> Показывать кандидатам</label>
        <button class="tn-btn" type="submit">{{ $track ? 'Сохранить' : 'Добавить направление' }}</button>
    </div>
</form>
