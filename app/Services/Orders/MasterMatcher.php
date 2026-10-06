<?php

namespace App\Services\Orders;

use App\Models\Applicant;
use App\Models\City;
use App\Models\UrgentOrder;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Каким мастерам сообщить о новой срочной заявке.
 *
 * Мастер — соискатель, у которого в портфолио есть работа этого вида. Город
 * в анкете соискателя — свободный текст («Худжанд», «г. Душанбе»), а в
 * заявке — запись справочника («Согдийская область, город Худжанд»), поэтому
 * сравниваем по названию самого города. Мастер без города в анкете тоже
 * получает уведомление: где он работает, неизвестно, а пропустить заказ хуже,
 * чем получить лишний.
 */
class MasterMatcher
{
    /**
     * Не больше стольких уведомлений на заявку. Очереди с обработчиком нет,
     * рассылка идёт в том же запросе, что и сохранение заявки, — он не должен
     * тянуться на тысячу записей.
     */
    public const NOTIFY_LIMIT = 50;

    /**
     * @return Collection<int, Applicant>
     */
    public function mastersFor(UrgentOrder $order, int $limit = self::NOTIFY_LIMIT): Collection
    {
        $needle = self::cityName($order->city);

        return Applicant::query()
            ->whereHas('portfolioWorks', fn (Builder $w) => $w->where('work_category_id', $order->work_category_id))
            // сам себе заказчик не мастер; заблокированным не пишем
            ->where('user_id', '!=', $order->user_id)
            ->whereHas('user', fn (Builder $u) => $u->activeAccount())
            ->when($needle !== null, fn (Builder $q) => $q->where(function (Builder $c) use ($needle) {
                $c->where('city', 'like', '%'.$needle.'%')
                    ->orWhereNull('city')
                    ->orWhere('city', '');
            }))
            // сначала те, кто недавно обновлял портфолио, — они активнее
            ->withMax('portfolioWorks', 'updated_at')
            ->orderByDesc('portfolio_works_max_updated_at')
            ->limit($limit)
            ->get();
    }

    /** Сообщить подходящим мастерам о новой заявке. Возвращает, скольким. */
    public function notify(UrgentOrder $order): int
    {
        $order->loadMissing('category', 'city');
        $masters = $this->mastersFor($order);

        foreach ($masters as $master) {
            UserNotification::deliver(
                $master->user_id,
                'order',
                UserNotification::text($order->isUrgent() ? 'Срочная заявка: :category' : 'Новая заявка: :category', [
                    'category' => $order->category?->name ?? '—',
                ]),
                Str::limit($order->title, 80).($order->place() ? ' · '.$order->place() : ''),
                route('public.orders.show', $order),
            );
        }

        return $masters->count();
    }

    /**
     * Название города из записи справочника: «Согдийская область, город
     * Худжанд» → «Худжанд», «Кулоб» → «Кулоб».
     */
    public static function cityName(?City $city): ?string
    {
        $region = trim((string) $city?->region);

        if ($region === '') {
            return null;
        }

        if (preg_match('/город\s+(.+)$/u', $region, $m)) {
            return trim($m[1]);
        }

        return trim(Str::afterLast($region, ','));
    }
}
