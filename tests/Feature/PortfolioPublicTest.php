<?php

use App\Models\PortfolioWork;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Support\Facades\Storage;

/*
 * Портфолио на публичных страницах (этап 3): профиль мастера, блок в резюме,
 * пометка и фильтр в каталоге.
 */

beforeEach(function () {
    Storage::fake('public');
});

/** Мастер с работой «до / после» в указанной категории. */
function publicMaster(string $category = 'plumbing', array $userAttributes = []): array
{
    $user = User::factory()->applicant()->create($userAttributes + ['name' => 'Рустам Мастеров']);
    $applicant = makeApplicant($user);
    $applicant->update(['city' => 'Душанбе', 'phone' => '+992 900 11 22 33', 'about_me' => 'Сантехник с 10-летним стажем']);

    $work = PortfolioWork::create([
        'applicant_id' => $applicant->id,
        'work_category_id' => WorkCategory::where('slug', $category)->value('id'),
        'title' => 'Замена стояка',
        'description' => 'Старые трубы протекали.',
        'done_at' => '2026-05-10',
    ]);
    $folder = $work->folder();
    $work->media()->create(['type' => 'photo', 'stage' => 'before', 'path' => "$folder/b.jpg", 'thumb_path' => "$folder/b_thumb.jpg", 'mime' => 'image/jpeg', 'size' => 1, 'position' => 1]);
    $work->media()->create(['type' => 'photo', 'stage' => 'after', 'path' => "$folder/a.jpg", 'thumb_path' => "$folder/a_thumb.jpg", 'mime' => 'image/jpeg', 'size' => 1, 'position' => 2]);
    $work->media()->create(['type' => 'video', 'path' => "$folder/v.mp4", 'poster_path' => "$folder/v_poster.jpg", 'mime' => 'video/mp4', 'size' => 1, 'position' => 3]);

    return [$user, $applicant, $work];
}

// ==================== профиль мастера ====================

test('профиль мастера открыт всем и показывает работы', function () {
    [, $applicant, $work] = publicMaster();

    $this->get(route('public.masters.show', $applicant))
        ->assertOk()
        ->assertSee('Рустам Мастеров')
        ->assertSee('Душанбе')
        ->assertSee('Сантехник с 10-летним стажем')
        ->assertSee('Замена стояка')
        ->assertSee('🚰 Сантехника')
        ->assertSee(__('До / после'))
        ->assertSee('+992 900 11 22 33')
        ->assertSee(asset('storage/'.$work->folder().'/a_thumb.jpg'), false);
});

test('данные галереи содержат пару до/после и видео, но не внутренние пути', function () {
    [, , $work] = publicMaster();

    $data = $work->load('media', 'category')->toGallery();

    expect($data['pair'])->toBe([
        'before' => asset('storage/'.$work->folder().'/b.jpg'),
        'after' => asset('storage/'.$work->folder().'/a.jpg'),
    ])
        ->and($data['items'])->toHaveCount(3)
        ->and($data['items'][2]['type'])->toBe('video')
        ->and($data['items'][2]['preview'])->toBe(asset('storage/'.$work->folder().'/v_poster.jpg'))
        ->and(json_encode($data))->not->toContain('applicant_id');
});

test('скрытый профиль мастера посторонним не виден, владельцу — с пометкой', function () {
    [$user, $applicant] = publicMaster();
    $user->settings()->create(['hide_profile' => true]);

    $this->get(route('public.masters.show', $applicant))->assertNotFound();

    $this->actingAs($user)->get(route('public.masters.show', $applicant))
        ->assertOk()
        ->assertSee(__('Профиль скрыт в настройках — эту страницу видите только вы.'));
});

test('скрытые контакты мастера не показываются', function () {
    [$user, $applicant] = publicMaster();
    $user->settings()->create(['hide_contacts' => true]);

    $this->get(route('public.masters.show', $applicant))
        ->assertOk()
        ->assertDontSee('+992 900 11 22 33');
});

test('заблокированный мастер не виден', function () {
    [$user, $applicant] = publicMaster();
    $user->forceFill(['status' => 'blocked'])->save();

    $this->get(route('public.masters.show', $applicant))->assertNotFound();
});

test('пустое портфолио посторонним не показывается, владельцу — подсказка', function () {
    $user = User::factory()->applicant()->create();
    $applicant = makeApplicant($user);

    $this->get(route('public.masters.show', $applicant))->assertNotFound();

    $this->actingAs($user)->get(route('public.masters.show', $applicant))
        ->assertOk()
        ->assertSee(route('applicant.portfolio.create'), false);
});

test('фильтр по виду работ появляется, когда видов больше одного', function () {
    [, $applicant] = publicMaster();

    $this->get(route('public.masters.show', $applicant))->assertDontSee('class="pg-filter"', false);

    PortfolioWork::create([
        'applicant_id' => $applicant->id,
        'work_category_id' => WorkCategory::where('slug', 'painting')->value('id'),
        'title' => 'Покраска фасада',
    ])->media()->create(['type' => 'photo', 'path' => 'x.jpg', 'mime' => 'image/jpeg', 'size' => 1]);

    $this->get(route('public.masters.show', $applicant))
        ->assertSee('class="pg-filter"', false)
        ->assertSee('🎨 Покраска · 1');
});

// ==================== резюме ====================

test('работы мастера показываются под резюме со ссылкой на профиль', function () {
    [, $applicant] = publicMaster();
    $resume = makeResume($applicant);

    $this->get(route('public.resumes.show', $resume))
        ->assertOk()
        ->assertSee(__('Выполненные работы'))
        ->assertSee('Замена стояка')
        ->assertSee(route('public.masters.show', $applicant), false);
});

test('профиль мастера ведёт к его резюме', function () {
    [, $applicant] = publicMaster();
    $resume = makeResume($applicant);

    $this->get(route('public.masters.show', $applicant))
        ->assertSee(route('public.resumes.show', $resume), false);
});

test('владельцу резюме без портфолио предлагают его добавить', function () {
    $user = User::factory()->applicant()->create();
    $resume = makeResume(makeApplicant($user));

    $this->actingAs($user)->get(route('public.resumes.show', $resume))
        ->assertOk()
        ->assertSee(__('Добавить работу в портфолио'));

    // посторонний пустой блок не видит
    $this->actingAs(User::factory()->employer()->create())->get(route('public.resumes.show', $resume))
        ->assertDontSee(__('Добавить работу в портфолио'));
});

// ==================== каталог ====================

test('в каталоге у резюме мастера есть пометка о портфолио', function () {
    [, $applicant] = publicMaster();
    makeResume($applicant);

    $this->get(route('public.resumes.index'))
        ->assertOk()
        ->assertSee(__('Портфолио: :count', ['count' => 1]))
        ->assertSee(route('public.masters.show', $applicant), false);
});

test('фильтр каталога по портфолио и виду работ', function () {
    [, $plumber] = publicMaster('plumbing', ['name' => 'Сантехник Один']);
    makeResume($plumber);
    [, $painter] = publicMaster('painting', ['name' => 'Маляр Два']);
    makeResume($painter);
    $plain = makeApplicant(User::factory()->applicant()->create(['name' => 'Без Портфолио']));
    makeResume($plain);

    $this->get(route('public.resumes.index'))
        ->assertSee('Сантехник Один')->assertSee('Маляр Два')->assertSee('Без Портфолио')
        // в фильтре только виды работ, по которым есть портфолио
        ->assertSee('🚰 Сантехника')->assertDontSee('🚗 Ремонт автомобилей');

    $this->get(route('public.resumes.index', ['work' => 'any']))
        ->assertSee('Сантехник Один')->assertSee('Маляр Два')->assertDontSee('Без Портфолио');

    $this->get(route('public.resumes.index', ['work' => 'painting']))
        ->assertDontSee('Сантехник Один')->assertSee('Маляр Два');

    // неизвестный вид работ — фильтр не применяется, а не пустой список
    $this->get(route('public.resumes.index', ['work' => 'nonsense']))
        ->assertOk()->assertSee('Без Портфолио');
});
