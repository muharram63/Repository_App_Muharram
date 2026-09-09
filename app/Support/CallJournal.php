<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Interview;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Журнал звонков кабинета. Строки собираются в PHP из встреч и приглашений
 * из чата, поэтому логика вынесена сюда: её просят и страница журнала,
 * и живые счётчики.
 */
class CallJournal
{
    /**
     * Сколько последних встреч разбираем.
     */
    private const LIMIT = 300;

    /**
     * Строки журнала, свежие сверху. Ничего не кэшируем внутри:
     * статический кэш пережил бы запрос в очереди или в Octane.
     * Вызывающий сам держит результат, если нужен дважды.
     */
    public static function rows(User $user): Collection
    {
        $employer = $user->employer;
        $applicant = $user->applicant;

        if (! $employer && ! $applicant) {
            return collect();
        }

        $interviews = Interview::with('employer.user', 'applicant.user', 'vacancy')
            ->when($employer, fn ($q) => $q->where('employer_id', $employer->id))
            ->when($applicant, fn ($q) => $q->where('applicant_id', $applicant->id))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        // каждое приглашение из чата — отдельный звонок в журнале
        $invites = Message::with('user')
            ->whereIn('interview_id', $interviews->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('interview_id');

        $conversations = Conversation::query()
            ->when($employer, fn ($q) => $q->where('employer_id', $employer->id))
            ->when($applicant, fn ($q) => $q->where('applicant_id', $applicant->id))
            ->get()
            ->keyBy(fn (Conversation $c) => $c->employer_id.'-'.$c->applicant_id);

        $rows = $interviews->flatMap(function (Interview $interview) use ($user, $employer, $invites, $conversations) {
            $conversation = $conversations->get($interview->employer_id.'-'.$interview->applicant_id);
            $list = $invites->get($interview->id);

            // у назначенной встречи приглашения из чата нет — это одна строка
            if ($list === null || $list->isEmpty()) {
                return [self::row($interview, null, $user, $employer, $conversation)];
            }

            return $list
                ->map(fn (Message $invite) => self::row($interview, $invite, $user, $employer, $conversation))
                ->all();
        })->sortByDesc('at')->values();

        return $rows;
    }

    /**
     * Цифры над журналом по уже собранным строкам.
     */
    public static function counts(Collection $rows): array
    {
        return [
            'all' => $rows->count(),
            'in' => $rows->where('direction', 'in')->count(),
            'out' => $rows->where('direction', 'out')->count(),
            'missed' => $rows->where('state', 'missed')->count(),
        ];
    }

    /**
     * Одна строка журнала: либо приглашение из чата, либо назначенная встреча.
     */
    private static function row(Interview $interview, ?Message $invite, User $user, $employer, ?Conversation $conversation): array
    {
        // звонок из чата — по автору приглашения, собеседование — по работодателю
        $outgoing = $invite
            ? $invite->user_id === $user->id
            : (bool) $employer;

        $counter = $employer ? $interview->applicant : $interview->employer;
        $counterUser = $counter?->user;

        return [
            'id' => $invite?->id ?? 'i'.$interview->id,
            'kind' => $invite ? 'chat' : 'scheduled',
            'direction' => $outgoing ? 'out' : 'in',
            'state' => $invite ? $invite->callState() : self::scheduledState($interview),
            'at' => $invite ? $invite->created_at : $interview->scheduled_at,
            'duration' => $interview->duration_minutes,
            'name' => $employer
                ? ($counterUser?->name ?? 'Соискатель')
                : ($counter?->company_name ?? $counterUser?->name ?? 'Компания'),
            'avatar' => $counterUser && $counterUser->hasAvatar() ? asset($counterUser->avatar) : null,
            'initials' => $employer ? ($counterUser?->initials(1) ?? '?') : ($counter?->initials(2) ?? '?'),
            'vacancy' => $interview->vacancy?->title,
            'roomOpen' => $interview->isRoomOpen(),
            'roomUrl' => route('public.interviews.show', $interview),
            'chatUrl' => $conversation ? route('public.chats.show', $conversation) : null,
            'callUrl' => $conversation ? route('public.chats.call', $conversation) : null,
        ];
    }

    /**
     * Состояние назначенного собеседования, у которого нет приглашения из чата.
     */
    private static function scheduledState(Interview $interview): string
    {
        if ($interview->status === 'canceled') {
            return 'canceled';
        }

        if ($interview->status === 'declined') {
            return 'declined';
        }

        if ($interview->answered_at) {
            return 'answered';
        }

        if ($interview->isRoomOpen()) {
            return 'ringing';
        }

        return $interview->isPast() ? 'missed' : 'planned';
    }
}
