<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Trainer\TrainerPackage;
use App\Models\Trainer\TrainerPayment;
use App\Models\Trainer\TrainerSession;
use App\Models\Trainer\TrainerTrack;
use App\Services\Trainer\TrainerException;
use App\Services\Trainer\TrainerPayments;
use App\Services\Trainer\TrainerWallet;
use Illuminate\Http\Request;

/**
 * Тренажёр собеседований — кабинет кандидата.
 *
 * Здесь всё, что видит кандидат: сколько у него попыток, начать тренировку,
 * история с баллами, покупка пакета и статус оплаты. Сама тренировка идёт на
 * страницах ИИ-собеседования — тем же голосом, тем же заданием, той же
 * проверкой кода, — но её результат видит только сам кандидат.
 */
class TrainerController extends Controller
{
    public function __construct(
        private readonly TrainerWallet $wallet,
        private readonly TrainerPayments $payments,
    ) {
    }

    public function index()
    {
        $user = auth()->user();

        // первая попытка — бесплатно, один раз на человека
        $freeNow = $this->wallet->grantFree($user);
        $limit = (int) config('trainer.daily_limit', 3);

        return view('applicant.pages.trainer.index', [
            'user' => $user,
            'applicant' => $user->applicant,
            'balance' => $this->wallet->balance($user),
            'freeNow' => $freeNow,
            'tracks' => TrainerTrack::active()->get(),
            'levels' => config('trainer.levels'),
            'packages' => TrainerPackage::active()->get(),
            'payments' => TrainerPayment::where('user_id', $user->id)->latest('id')->take(10)->get(),
            'sessions' => TrainerSession::where('user_id', $user->id)->latest('id')->get(),
            'dailyLeft' => $limit > 0 ? max(0, $limit - $this->wallet->startedToday($user)) : null,
        ]);
    }

    /** Выбрать пакет: создаётся заявка, и кандидат видит реквизиты. */
    public function buy(TrainerPackage $package)
    {
        try {
            $payment = $this->payments->create(auth()->user(), $package);
        } catch (TrainerException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('applicant.trainer.payment', $payment);
    }

    /** Заявка на оплату: реквизиты, сумма, назначение платежа, «Я оплатил». */
    public function payment(TrainerPayment $payment)
    {
        $this->mine($payment->user_id);

        return view('applicant.pages.trainer.payment', [
            'user' => auth()->user(),
            'applicant' => auth()->user()->applicant,
            'payment' => $payment,
            'pay' => $this->payments->gateway()->instructions($payment),
        ]);
    }

    /** Кандидат перевёл деньги и нажал «Я оплатил». */
    public function claim(Request $request, TrainerPayment $payment)
    {
        $this->mine($payment->user_id);

        $validated = $request->validate([
            // по чему администратор найдёт перевод: номер, с которого платили
            'payer_note' => 'nullable|string|max:255',
        ]);

        $this->payments->claim($payment, $validated['payer_note'] ?? null);

        return redirect()->route('applicant.trainer.payment', $payment)
            ->with('status', __('Спасибо! Мы проверим оплату и начислим попытки — придёт уведомление.'));
    }

    private function mine(int $userId): void
    {
        abort_if($userId !== auth()->id(), 403);
    }
}
