<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\UrgentOrderOffer;
use App\Models\UserNotification;
use Illuminate\Support\Str;
use App\Services\Orders\MasterMatcher;
use App\Models\UrgentOrder;
use App\Models\WorkCategory;
use App\Services\Media\MediaRejected;
use App\Services\Media\MediaStore;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Срочные заявки заказчика: создать, посмотреть свои, отменить.
 *
 * Заказчиком может быть любой вошедший пользователь — и работодатель, и
 * соискатель: у человека со сломанной стиральной машиной компании нет.
 * Лента для мастеров и отклики — этап 5, выбор исполнителя — этап 6.
 */
class UrgentOrderController extends Controller
{
    public function __construct(private readonly MediaStore $store)
    {
    }

    public function mine(Request $request)
    {
        // просроченные закрываем до показа — планировщик запущен не всегда
        app(\App\Services\Orders\OrderExpiry::class)->sweepSoon();

        $orders = UrgentOrder::where('user_id', $request->user()->id)
            ->with(['category', 'city', 'photos'])
            ->withCount(['offers as active_offers_count' => fn ($o) => $o->whereIn('status', [
                UrgentOrderOffer::STATUS_PENDING, UrgentOrderOffer::STATUS_CHOSEN,
            ])])
            // открытые — сверху, по ближайшему сроку; остальные — по свежести
            ->orderByRaw('case when status = ? then 0 else 1 end', [UrgentOrder::STATUS_OPEN])
            ->orderByRaw('case when status = ? then deadline_at end asc', [UrgentOrder::STATUS_OPEN])
            ->latest('id')
            ->paginate(20);

        return view('public.pages.orders.mine', [
            'orders' => $orders,
            'canCreate' => $this->openCount($request) < UrgentOrder::MAX_OPEN_PER_USER,
        ]);
    }

    public function create(Request $request)
    {
        if ($this->openCount($request) >= UrgentOrder::MAX_OPEN_PER_USER) {
            return redirect()->route('public.orders.mine')->with('error', $this->limitMessage());
        }

        // «Повторить»: поля прошлой своей заявки подставляются в форму
        $repeat = $request->integer('repeat')
            ? UrgentOrder::where('user_id', $request->user()->id)->find($request->integer('repeat'))
            : null;

        return view('public.pages.orders.create', [
            'prefill' => $repeat ? $repeat->only([
                'title', 'work_category_id', 'description', 'city_id', 'district', 'address', 'contact_phone', 'budget',
            ]) : [],
            'categories' => WorkCategory::ordered()->get(),
            'cities' => City::orderBy('region')->get(),
            'deadlines' => UrgentOrder::DEADLINES,
            'maxPhotos' => UrgentOrder::MAX_PHOTOS,
            'photoMb' => MediaStore::MAX_PHOTO_KB / 1024,
            'photoMimes' => MediaStore::PHOTO_MIMES,
            // телефон из анкеты — чтобы не вводить заново
            'defaultPhone' => $request->user()->employer?->phone ?: $request->user()->applicant?->phone,
        ]);
    }

    public function store(Request $request)
    {
        if ($this->openCount($request) >= UrgentOrder::MAX_OPEN_PER_USER) {
            throw ValidationException::withMessages(['title' => $this->limitMessage()]);
        }

        $data = $request->validate([
            'title' => 'required|string|min:3|max:120',
            'work_category_id' => 'required|integer|exists:work_categories,id',
            'description' => 'required|string|min:10|max:2000',
            'city_id' => 'required|integer|exists:cities,id',
            // номер увидит только выбранный мастер
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'district' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
            'deadline_option' => 'required|in:'.implode(',', array_keys(UrgentOrder::DEADLINES)),
            'budget' => 'nullable|integer|min:1|max:10000000',
            'photos' => 'nullable|array|max:'.UrgentOrder::MAX_PHOTOS,
            'photos.*' => 'file|max:'.MediaStore::MAX_PHOTO_KB,
        ], [
            'photos.max' => __('Фото проблемы — не больше :max.'),
            'photos.*.max' => __('Фото больше 10 МБ.'),
            'photos.*.uploaded' => __('Фото не загрузилось: оно слишком большое или связь прервалась.'),
            'contact_phone.regex' => __('Телефон — только цифры, пробелы, скобки, «+» и «-».'),
        ]);

        $photos = $this->checkPhotos($request);

        $order = DB::transaction(fn () => UrgentOrder::create(Arr::only($data, [
            'title', 'work_category_id', 'description', 'city_id', 'district', 'address', 'contact_phone', 'deadline_option', 'budget',
        ]) + [
            'user_id' => $request->user()->id,
            'deadline_at' => UrgentOrder::deadlineFor($data['deadline_option']),
            'status' => UrgentOrder::STATUS_OPEN,
        ]));

        try {
            foreach (array_values($photos) as $position => $file) {
                $stored = $this->store->storePhoto($file, $order->folder());

                $order->photos()->create(Arr::only($stored, ['path', 'thumb_path', 'width', 'height']) + [
                    'position' => $position,
                ]);
            }
        } catch (MediaRejected $e) {
            // заявка без своих фото — не та, что задумал человек: пусть отправит заново
            $order->delete();

            throw ValidationException::withMessages(['photos' => $e->getMessage()]);
        }

        // мастерам нужного профиля в этом городе — уведомление со звуком
        app(MasterMatcher::class)->notify($order);

        return redirect()->route('public.orders.show', $order)
            ->with('status', __('Заявка опубликована. Мастера увидят её и откликнутся.'));
    }

    public function show(Request $request, UrgentOrder $order)
    {
        // срок вышел, а планировщик ещё не успел — закрываем прямо сейчас
        if ($order->isOpen() && $order->deadline_at->isPast()) {
            app(\App\Services\Orders\OrderExpiry::class)->expire($order);
            $order->refresh();
        }

        $user = $request->user();
        $isOwner = $order->isOwnedBy($user);
        $myOffer = $user?->applicant
            ? $order->offers()->where('applicant_id', $user->applicant->id)->first()
            : null;

        // открытую заявку видят все, и гости тоже; закрытую — заказчик,
        // администратор и мастера, которые на неё откликались
        abort_unless(
            $order->isOpen() || $isOwner || $user?->role === 'admin' || $myOffer,
            404
        );

        $order->load(['category', 'city', 'photos', 'user.employer', 'user.applicant', 'chosenOffer.applicant.user']);

        // отклики целиком видит только заказчик (и администратор): мастерам
        // чужие цены ни к чему
        $offers = $isOwner || $user?->role === 'admin'
            ? $order->offers()
                ->where('status', '!=', UrgentOrderOffer::STATUS_WITHDRAWN)
                ->with(['applicant.user', 'applicant.portfolioWorks.media', 'applicant.portfolioWorks.category'])
                ->get()
            : collect();

        return view('public.pages.orders.show', [
            'order' => $order,
            'isOwner' => $isOwner,
            'showAddress' => $order->addressVisibleTo($user),
            'offers' => $offers,
            'myOffer' => $myOffer,
            'cannotOffer' => $isOwner ? null : UrgentOrderOfferController::cannotOffer($user, $order),
            'canStart' => UrgentOrderOffer::CAN_START,
            'isChosenMaster' => $order->isChosenMaster($user),
        ]);
    }

    /**
     * Выбрать исполнителя. Выбранному открываются адрес и телефон, остальным
     * откликнувшимся — вежливое «выбран другой мастер».
     *
     * Под блокировкой: два быстрых нажатия «Выбрать» у разных мастеров не
     * должны дать двух исполнителей.
     */
    public function choose(Request $request, UrgentOrder $order, UrgentOrderOffer $offer)
    {
        abort_unless($order->isOwnedBy($request->user()), 404);
        abort_unless($offer->urgent_order_id === $order->id, 404);

        $result = DB::transaction(function () use ($order, $offer) {
            $order = UrgentOrder::lockForUpdate()->find($order->id);
            $offer = UrgentOrderOffer::lockForUpdate()->find($offer->id);

            if (! $order->isOpen()) {
                return __('Исполнитель уже выбран или заявка закрыта.');
            }

            if ($offer->status !== UrgentOrderOffer::STATUS_PENDING) {
                return __('Этот отклик уже неактивен — мастер его отозвал.');
            }

            $others = UrgentOrderOffer::where('urgent_order_id', $order->id)
                ->where('id', '!=', $offer->id)
                ->where('status', UrgentOrderOffer::STATUS_PENDING)
                ->with('applicant')
                ->get();

            $order->update(['status' => UrgentOrder::STATUS_ASSIGNED, 'chosen_offer_id' => $offer->id]);
            $offer->update(['status' => UrgentOrderOffer::STATUS_CHOSEN]);
            UrgentOrderOffer::whereIn('id', $others->pluck('id'))->update(['status' => UrgentOrderOffer::STATUS_DECLINED]);

            return [$order, $offer->load('applicant'), $others];
        });

        if (is_string($result)) {
            return back()->with('error', $result);
        }

        [$order, $offer, $others] = $result;
        $title = Str::limit($order->title, 60);

        UserNotification::deliver(
            $offer->applicant?->user_id,
            'order',
            UserNotification::text('Вас выбрали исполнителем'),
            UserNotification::text('Заявка «:title». Адрес и телефон заказчика — на странице заявки.', ['title' => $title]),
            route('public.orders.show', $order),
        );

        foreach ($others as $other) {
            UserNotification::deliver(
                $other->applicant?->user_id,
                'order',
                UserNotification::text('Заказчик выбрал другого мастера'),
                UserNotification::text('По заявке «:title». Спасибо за отклик — новые заказы появляются каждый день.', ['title' => $title]),
                route('public.orders.index', ['mine' => 1]),
            );
        }

        return redirect()->route('public.orders.show', $order)
            ->with('status', __('Исполнитель выбран. Ему открыты адрес и ваш телефон — он свяжется с вами. Его номер — ниже.'));
    }

    /** Работа сделана — заказчик закрывает заявку. */
    public function complete(Request $request, UrgentOrder $order)
    {
        abort_unless($order->isOwnedBy($request->user()), 404);

        if (! $order->isAssigned()) {
            return back()->with('error', __('Отметить выполненной можно заявку с выбранным исполнителем.'));
        }

        $order->update(['status' => UrgentOrder::STATUS_DONE, 'closed_at' => now()]);

        UserNotification::deliver(
            $order->chosenOffer?->applicant?->user_id,
            'order',
            UserNotification::text('Заказ отмечен выполненным'),
            UserNotification::text('Заказчик подтвердил выполнение заявки «:title». Спасибо за работу!', ['title' => Str::limit($order->title, 60)]),
            route('public.orders.show', $order),
        );

        return redirect()->route('public.orders.show', $order)->with('status', __('Заявка закрыта как выполненная. Спасибо!'));
    }

    public function cancel(Request $request, UrgentOrder $order)
    {
        abort_unless($order->isOwnedBy($request->user()), 404);

        if (! $order->isOpen() && ! $order->isAssigned()) {
            return back()->with('error', __('Эта заявка уже закрыта.'));
        }

        // мастерам, которые ждут ответа или уже собираются ехать, — сообщить
        $waiting = $order->offers()
            ->whereIn('status', [UrgentOrderOffer::STATUS_PENDING, UrgentOrderOffer::STATUS_CHOSEN])
            ->with('applicant')
            ->get();

        DB::transaction(function () use ($order, $waiting) {
            $order->update([
                'status' => UrgentOrder::STATUS_CANCELLED,
                'closed_at' => now(),
            ]);

            UrgentOrderOffer::whereIn('id', $waiting->pluck('id'))->update(['status' => UrgentOrderOffer::STATUS_CANCELLED]);
        });

        foreach ($waiting as $offer) {
            UserNotification::deliver(
                $offer->applicant?->user_id,
                'order',
                UserNotification::text('Заказчик отменил заявку'),
                UserNotification::text('Заявка «:title» больше не актуальна.', ['title' => Str::limit($order->title, 60)]),
                route('public.orders.index', ['mine' => 1]),
            );
        }

        return redirect()->route('public.orders.mine')->with('status', __('Заявка отменена.'));
    }

    /**
     * Фото проверяем по содержимому до создания заявки — чтобы не создавать
     * её и не удалять, если прислали не фото.
     *
     * @return array<int, UploadedFile>
     */
    private function checkPhotos(Request $request): array
    {
        $photos = [];
        $errors = [];

        foreach ((array) $request->file('photos', []) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if ($this->store->kind($file) !== 'photo') {
                $errors['photos.'.$index] = __('«:name» — не фото. Подходят JPG, PNG, WEBP.', ['name' => $file->getClientOriginalName()]);

                continue;
            }

            $photos[$index] = $file;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $photos;
    }

    private function openCount(Request $request): int
    {
        return UrgentOrder::where('user_id', $request->user()->id)->open()->count();
    }

    private function limitMessage(): string
    {
        return __('У вас уже :count открытых заявок. Дождитесь мастера или отмените одну из них.', ['count' => UrgentOrder::MAX_OPEN_PER_USER]);
    }
}
