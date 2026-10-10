<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trainer\TrainerLedgerEntry;
use App\Models\Trainer\TrainerPackage;
use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSession;
use App\Models\Trainer\TrainerSetting;
use App\Models\Trainer\TrainerTrack;
use App\Models\User;
use App\Services\Trainer\TrainerPayments;
use App\Services\Trainer\TrainerWallet;
use Illuminate\Http\Request;

/**
 * Админка «Тренажёр»: заявки на оплату, ручное начисление попыток, пакеты,
 * реквизиты, направления и статистика.
 *
 * Ответы и разборы кандидатов здесь не показываются — только то, что нужно
 * для денег и настройки: кто сколько заплатил и сколько попыток получил.
 */
class AdminTrainerController extends Controller
{
    public function __construct(
        private readonly TrainerPayments $payments,
        private readonly TrainerWallet $wallet,
    ) {
    }

    public function index(Request $request)
    {
        $filter = in_array($request->query('payments'), ['open', 'confirmed', 'rejected', 'all'], true)
            ? $request->query('payments') : 'open';

        $payments = TrainerPayment::with('user')
            ->when($filter === 'open', fn ($q) => $q->whereIn('status', [TrainerPayment::PENDING, TrainerPayment::CLAIMED]))
            ->when(in_array($filter, ['confirmed', 'rejected'], true), fn ($q) => $q->where('status', $filter))
            // «Я оплатил» — наверх: их пора проверять
            ->orderByRaw("CASE WHEN status = 'claimed' THEN 0 ELSE 1 END")
            ->latest('id')
            ->take(100)
            ->get();

        return view('admin.pages.trainer.index', [
            'payments' => $payments,
            'filter' => $filter,
            'packages' => TrainerPackage::orderBy('position')->orderBy('price')->get(),
            'tracks' => TrainerTrack::orderBy('position')->orderBy('title')->get(),
            'settings' => collect(TrainerSetting::KEYS)->mapWithKeys(fn ($label, $key) => [$key => TrainerSetting::get($key)]),
            'stats' => $this->stats(),
            'ledger' => TrainerLedgerEntry::with('user')->latest('id')->take(30)->get(),
        ]);
    }

    // ==================== оплата ====================

    public function confirm(Request $request, TrainerPayment $payment)
    {
        $done = $this->payments->confirm($payment, $request->user());

        return back()->with('status', $done
            ? __('Оплата :ref подтверждена, начислено попыток: :n.', ['ref' => $payment->reference(), 'n' => $payment->attempts])
            : __('Заявка :ref уже решена.', ['ref' => $payment->reference()]));
    }

    public function reject(Request $request, TrainerPayment $payment)
    {
        $validated = $request->validate(['reason' => 'required|string|min:3|max:255']);

        $done = $this->payments->reject($payment, $request->user(), $validated['reason']);

        return back()->with('status', $done
            ? __('Заявка :ref отклонена.', ['ref' => $payment->reference()])
            : __('Заявка :ref уже решена.', ['ref' => $payment->reference()]));
    }

    /** Начислить попытки вручную: по email кандидата, с пометкой — зачем. */
    public function grant(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'attempts' => 'required|integer|min:1|max:100',
            'note' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || $user->role !== 'applicant') {
            return back()->withInput()->with('error', __('Кандидат с таким email не найден.'));
        }

        $this->wallet->credit($user, (int) $validated['attempts'], 'manual', null, $validated['note'] ?? null, $request->user()->id);

        return back()->with('status', __('Начислено :n попыток: :email.', ['n' => $validated['attempts'], 'email' => $user->email]));
    }

    // ==================== пакеты и реквизиты ====================

    public function savePackage(Request $request, ?TrainerPackage $package = null)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'attempts' => 'required|integer|min:1|max:100',
            'price' => 'required|integer|min:0|max:1000000',
            'position' => 'nullable|integer|min:0|max:1000',
        ]);

        $data = $validated + ['active' => $request->boolean('active'), 'position' => (int) ($validated['position'] ?? 0)];

        $package && $package->exists ? $package->update($data) : TrainerPackage::create($data);

        return back()->with('status', __('Пакет сохранён.'));
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'wallet' => 'nullable|string|max:120',
            'recipient' => 'nullable|string|max:120',
            'instructions' => 'nullable|string|max:2000',
        ]);

        foreach (array_keys(TrainerSetting::KEYS) as $key) {
            TrainerSetting::put($key, $validated[$key] ?? null);
        }

        return back()->with('status', __('Реквизиты сохранены.'));
    }

    // ==================== статистика ====================

    private function stats(): array
    {
        $real = TrainerSession::where('demo', false);

        return [
            'sessions' => (clone $real)->count(),
            'sessions_today' => (clone $real)->where('created_at', '>=', now()->startOfDay())->count(),
            'finished' => (clone $real)->where('status', TrainerSession::STATUS_FINISHED)->count(),
            'refunded' => (clone $real)->where('status', TrainerSession::STATUS_REFUNDED)->count(),
            'avg_score' => (int) round((float) (clone $real)->whereNotNull('score')->avg('score')),
            'trainees' => (clone $real)->distinct('user_id')->count('user_id'),
            'paid' => TrainerPayment::where('status', TrainerPayment::CONFIRMED)->count(),
            'revenue' => (int) TrainerPayment::where('status', TrainerPayment::CONFIRMED)->sum('amount'),
            'revenue_month' => (int) TrainerPayment::where('status', TrainerPayment::CONFIRMED)
                ->where('decided_at', '>=', now()->startOfMonth())->sum('amount'),
            'open' => TrainerPayment::whereIn('status', [TrainerPayment::PENDING, TrainerPayment::CLAIMED])->count(),
            'claimed' => TrainerPayment::where('status', TrainerPayment::CLAIMED)->count(),
        ];
    }
}
