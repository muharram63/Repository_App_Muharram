<?php

use App\Models\PortfolioMedia;
use App\Models\PortfolioWork;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Портфолио мастера в кабинете соискателя (этап 2).
 */

beforeEach(function () {
    Storage::fake('public');
});

function master(): array
{
    $user = User::factory()->applicant()->create();

    return [$user, makeApplicant($user)];
}

function plumbingId(): int
{
    return WorkCategory::where('slug', 'plumbing')->value('id');
}

/** Настоящее начало MP4-файла: по нему fileinfo узнаёт видео. */
function fakeVideo(string $name = 'work.mp4'): UploadedFile
{
    $bytes = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 2048);

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

/** JPEG с EXIF: как снимок с телефона, в котором записаны GPS-координаты. */
function photoWithExif(): UploadedFile
{
    $image = imagecreatetruecolor(1200, 900);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    // сегмент APP1 «Exif» сразу после SOI — минимальный, но настоящий
    $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00";
    $payload = "Exif\x00\x00".$tiff.'GPS 38.5598 68.7870';
    $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return UploadedFile::fake()->createWithContent('phone.jpg', substr($jpeg, 0, 2).$app1.substr($jpeg, 2));
}

function workPayload(array $extra = []): array
{
    return $extra + [
        'title' => 'Замена труб в ванной',
        'work_category_id' => plumbingId(),
        'description' => 'Заменил стояк на полипропилен.',
    ];
}

// ==================== доступ ====================

test('страница портфолио открывается в кабинете и есть в меню', function () {
    [$user] = master();

    $this->actingAs($user)->get(route('applicant.portfolio.index'))
        ->assertOk()
        ->assertSee(__('Портфолио пока пустое'))
        ->assertSee(route('applicant.portfolio.index'), false);
});

test('работодателю кабинет портфолио мастера закрыт', function () {
    $user = User::factory()->employer()->create();

    $this->actingAs($user)->get(route('applicant.portfolio.index'))->assertForbidden();
});

test('гостя отправляют войти', function () {
    $this->get(route('applicant.portfolio.index'))->assertRedirect(route('login'));
});

test('чужую работу не открыть, не изменить и не удалить', function () {
    [, $owner] = master();
    $work = PortfolioWork::create(['applicant_id' => $owner->id, 'title' => 'Чужая', 'work_category_id' => plumbingId()]);
    [$intruder] = master();

    $this->actingAs($intruder)->get(route('applicant.portfolio.edit', $work))->assertNotFound();
    $this->actingAs($intruder)->put(route('applicant.portfolio.update', $work), workPayload())->assertNotFound();
    $this->actingAs($intruder)->delete(route('applicant.portfolio.destroy', $work))->assertNotFound();

    expect($work->fresh())->not->toBeNull();
});

// ==================== загрузка ====================

test('работа с фото до и после и видео сохраняется', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [
            UploadedFile::fake()->image('before.jpg', 1600, 1200),
            UploadedFile::fake()->image('after.png', 800, 600),
            fakeVideo(),
        ],
        'stages' => ['before', 'after', ''],
        'posters' => [2 => UploadedFile::fake()->image('poster.jpg', 1280, 720)],
    ]))->assertRedirect(route('applicant.portfolio.index'));

    $work = $applicant->portfolioWorks()->with('media')->sole();
    [$before, $after, $video] = $work->media->all();

    expect($work->title)->toBe('Замена труб в ванной')
        ->and($work->hasBeforeAfter())->toBeTrue()
        ->and($before->stage)->toBe('before')
        ->and($after->type)->toBe('photo')
        // фото уменьшено до 2000 по длинной стороне не было — оно меньше; пересохранено в JPEG
        ->and($after->mime)->toBe('image/jpeg')
        ->and($video->type)->toBe('video')
        ->and($video->mime)->toBe('video/mp4')
        ->and($video->poster_path)->not->toBeNull()
        ->and(str_starts_with($video->path, 'portfolio/'.$applicant->id.'/'.$work->id.'/'))->toBeTrue();

    foreach ([$before->path, $before->thumb_path, $video->path, $video->poster_path] as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('скрипт страницы получает адрес для перехода, а не переадресацию', function () {
    [$user] = master();

    $this->actingAs($user)
        ->postJson(route('applicant.portfolio.store'), workPayload(['media' => [UploadedFile::fake()->image('a.jpg')]]))
        ->assertOk()
        ->assertJsonPath('redirect', route('applicant.portfolio.index'));
});

test('большое фото уменьшается, миниатюра маленькая', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->image('big.jpg', 4000, 3000)],
    ]));

    $media = $applicant->portfolioWorks()->sole()->media->sole();
    $thumb = getimagesizefromstring(Storage::disk('public')->get($media->thumb_path));

    expect($media->width)->toBe(2000)
        ->and($media->height)->toBe(1500)
        ->and(max($thumb[0], $thumb[1]))->toBe(480);
});

test('из фото удаляются EXIF-данные с координатами', function () {
    [$user, $applicant] = master();

    $upload = photoWithExif();
    expect(file_get_contents($upload->getRealPath()))->toContain('GPS 38.5598');

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload(['media' => [$upload]]))
        ->assertRedirect();

    $media = $applicant->portfolioWorks()->sole()->media->sole();

    expect(Storage::disk('public')->get($media->path))->not->toContain('GPS 38.5598')
        ->and(Storage::disk('public')->get($media->path))->not->toContain('Exif');
});

test('без единого файла новую работу не сохранить', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload())
        ->assertSessionHasErrors('media');

    expect($applicant->portfolioWorks()->count())->toBe(0);
});

test('скрипт, переименованный в jpg, не проходит', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->createWithContent('photo.jpg', '<?php system($_GET["c"]); ?>')],
    ]))->assertSessionHasErrors('media.0');

    expect($applicant->portfolioWorks()->count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('видео, которое внутри не видео, не проходит', function () {
    [$user] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->createWithContent('clip.mp4', str_repeat('A', 4096))],
    ]))->assertSessionHasErrors('media.0');
});

test('фото больше 10 МБ не проходит', function () {
    [$user] = master();

    $big = UploadedFile::fake()->image('big.jpg', 100, 100)->size(11 * 1024);

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload(['media' => [$big]]))
        ->assertSessionHasErrors('media.0');
});

test('файл больше 100 МБ не проходит', function () {
    [$user] = master();

    $huge = UploadedFile::fake()->create('huge.mp4', 101 * 1024, 'video/mp4');

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload(['media' => [$huge]]))
        ->assertSessionHasErrors('media.0');
});

test('больше двух видео в одной работе нельзя', function () {
    [$user] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [fakeVideo('a.mp4'), fakeVideo('b.mp4'), fakeVideo('c.mp4')],
    ]))->assertSessionHasErrors('media');
});

test('больше десяти фото в одной работе нельзя — с учётом уже загруженных', function () {
    [$user, $applicant] = master();
    $work = PortfolioWork::create(['applicant_id' => $applicant->id, 'title' => 'Покраска', 'work_category_id' => plumbingId()]);

    foreach (range(1, 9) as $i) {
        $work->media()->create(['type' => 'photo', 'path' => "p{$i}.jpg", 'mime' => 'image/jpeg', 'size' => 1]);
    }

    $this->actingAs($user)->put(route('applicant.portfolio.update', $work), workPayload([
        'media' => [UploadedFile::fake()->image('10.jpg'), UploadedFile::fake()->image('11.jpg')],
    ]))->assertSessionHasErrors('media');

    expect($work->media()->count())->toBe(9);
});

test('категория должна быть из справочника', function () {
    [$user] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'work_category_id' => 9999,
        'media' => [UploadedFile::fake()->image('a.jpg')],
    ]))->assertSessionHasErrors('work_category_id');
});

// ==================== правка и удаление ====================

test('правка меняет поля и отметки, добавляет файлы', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->image('a.jpg')],
    ]));
    $work = $applicant->portfolioWorks()->sole();
    $first = $work->media->sole();

    $this->actingAs($user)->get(route('applicant.portfolio.edit', $work))
        ->assertOk()
        ->assertSee($work->title)
        ->assertSee($first->previewUrl(), false);

    $this->actingAs($user)->put(route('applicant.portfolio.update', $work), workPayload([
        'title' => 'Замена стояка',
        'existing_stage' => [$first->id => 'before'],
        'media' => [UploadedFile::fake()->image('b.jpg')],
        'stages' => ['after'],
    ]))->assertRedirect(route('applicant.portfolio.index'));

    $work->refresh()->load('media');

    expect($work->title)->toBe('Замена стояка')
        ->and($work->media)->toHaveCount(2)
        ->and($work->hasBeforeAfter())->toBeTrue();
});

test('удаление файла убирает его с диска', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]));
    $work = $applicant->portfolioWorks()->sole();
    $media = $work->media->first();

    $this->actingAs($user)->delete(route('applicant.portfolio.media.destroy', [$work, $media]))
        ->assertRedirect(route('applicant.portfolio.edit', $work));

    Storage::disk('public')->assertMissing($media->path);
    Storage::disk('public')->assertMissing($media->thumb_path);
    expect($work->media()->count())->toBe(1);
});

test('файл другой работы через чужой адрес не удалить', function () {
    [$user, $applicant] = master();
    $mine = PortfolioWork::create(['applicant_id' => $applicant->id, 'title' => 'Моя', 'work_category_id' => plumbingId()]);

    [, $other] = master();
    $foreign = PortfolioWork::create(['applicant_id' => $other->id, 'title' => 'Чужая', 'work_category_id' => plumbingId()]);
    $media = $foreign->media()->create(['type' => 'photo', 'path' => 'x.jpg', 'mime' => 'image/jpeg', 'size' => 1]);

    $this->actingAs($user)->delete(route('applicant.portfolio.media.destroy', [$mine, $media]))->assertNotFound();

    expect($media->fresh())->not->toBeNull();
});

test('удаление работы убирает все её файлы', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->image('a.jpg'), fakeVideo()],
    ]));
    $work = $applicant->portfolioWorks()->sole();

    $this->actingAs($user)->delete(route('applicant.portfolio.destroy', $work))
        ->assertRedirect(route('applicant.portfolio.index'));

    expect(PortfolioMedia::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles($work->folder()))->toBe([]);
});

test('удаление аккаунта убирает файлы портфолио', function () {
    [$user, $applicant] = master();
    $user->update(['password' => bcrypt('secret-pass')]);

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->image('a.jpg')],
    ]));
    expect(Storage::disk('public')->allFiles(PortfolioWork::applicantFolder($applicant->id)))->not->toBe([]);

    $this->actingAs($user)->delete(route('applicant.settings.destroy'), ['password' => 'secret-pass']);

    expect(User::find($user->id))->toBeNull()
        ->and(Storage::disk('public')->allFiles(PortfolioWork::applicantFolder($applicant->id)))->toBe([]);
});

test('список показывает работу с обложкой и счётчиками', function () {
    [$user, $applicant] = master();

    $this->actingAs($user)->post(route('applicant.portfolio.store'), workPayload([
        'media' => [UploadedFile::fake()->image('before.jpg'), UploadedFile::fake()->image('after.jpg')],
        'stages' => ['before', 'after'],
    ]));
    $work = $applicant->portfolioWorks()->with('media')->sole();

    $this->actingAs($user)->get(route('applicant.portfolio.index'))
        ->assertOk()
        ->assertSee('Замена труб в ванной')
        ->assertSee(__('До / после'))
        ->assertSee($work->cover()->previewUrl(), false)
        ->assertSee(WorkCategory::find(plumbingId())->title());
});

test('ссылки на файлы строятся от адреса сайта, а не от APP_URL', function () {
    config(['app.url' => 'http://localhost']);

    $media = new PortfolioMedia(['type' => 'photo', 'path' => 'portfolio/1/2/a.jpg', 'thumb_path' => 'portfolio/1/2/a_thumb.jpg']);

    $this->get('http://127.0.0.1:8000/');

    expect($media->previewUrl())->toBe(asset('storage/portfolio/1/2/a_thumb.jpg'));
});

test('запасной маршрут отдаёт файл портфолио', function () {
    Storage::disk('public')->put('portfolio/5/7/abc.jpg', 'jpeg-bytes');

    $this->get('/storage/portfolio/5/7/abc.jpg')->assertOk();
    $this->get('/storage/portfolio/5/7/nope.jpg')->assertNotFound();
});
