<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;

class CallController extends Controller
{
    /**
     * Отклонить входящий звонок: комната закрывается у обеих сторон,
     * а в переписке появляется отметка.
     */
    public function decline(\App\Models\Interview $interview)
    {
        $user = auth()->user();

        $isEmployer = $user->employer && $user->employer->id === $interview->employer_id;
        $isApplicant = $user->applicant && $user->applicant->id === $interview->applicant_id;

        abort_if(! $isEmployer && ! $isApplicant, 403);

        $interview->update(['status' => 'declined']);

        $conversation = Conversation::where('employer_id', $interview->employer_id)
            ->where('applicant_id', $interview->applicant_id)
            ->first();

        if ($conversation) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'body' => '📵 Звонок отклонён',
            ]);

            $conversation->update(['last_message_at' => now()]);
        }

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['declined' => true]);
        }

        return back()->with('status', 'Звонок отклонён.');
    }

    /**
     * Состояние встречи — страница звонка опрашивает его,
     * чтобы закрыть комнату, если собеседник отклонил или отменил.
     */
    public function status(\App\Models\Interview $interview)
    {
        $user = auth()->user();

        $isEmployer = $user->employer && $user->employer->id === $interview->employer_id;
        $isApplicant = $user->applicant && $user->applicant->id === $interview->applicant_id;

        abort_if(! $isEmployer && ! $isApplicant, 403);

        return response()->json([
            'status' => $interview->status,
            'label' => $interview->statusLabel(),
            'open' => $interview->isRoomOpen(),
        ]);
    }

    /**
     * Входящий звонок: приглашение, которое собеседник создал только что
     * и комната по которому ещё открыта.
     */
    public function incoming()
    {
        $user = auth()->user();

        $conversationIds = Conversation::query()
            ->when($user->employer, fn ($q) => $q->where('employer_id', $user->employer->id))
            ->when($user->applicant, fn ($q) => $q->where('applicant_id', $user->applicant->id))
            ->when(! $user->employer && ! $user->applicant, fn ($q) => $q->whereRaw('1 = 0'))
            ->pluck('id');

        // моё последнее приглашение: если я звонил позже собеседника,
        // значит инициатива моя и входящего показывать не нужно
        $myLastInviteId = (int) Message::whereIn('conversation_id', $conversationIds)
            ->whereNotNull('interview_id')
            ->where('user_id', $user->id)
            ->max('id');

        $invite = Message::with('interview', 'user')
            ->whereIn('conversation_id', $conversationIds)
            ->whereNotNull('interview_id')
            ->where('user_id', '!=', $user->id)
            ->where('id', '>', $myLastInviteId)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->latest('id')
            ->first();

        $call = $invite && $invite->interview && $invite->interview->isRoomOpen()
            ? [
                'id' => $invite->interview->id,
                'caller' => $invite->user?->name ?? 'Собеседник',
                'url' => route('public.interviews.show', $invite->interview),
                'chatUrl' => route('public.chats.show', $invite->conversation_id),
            ]
            : null;

        return response()->json([
            'call' => $call,
            'missed' => $call ? null : $this->missedCall($conversationIds),
        ]);
    }

    /**
     * Последний звонок, на который пользователь не ответил.
     */
    private function missedCall($conversationIds): ?array
    {
        $missed = Message::with('interview', 'user')
            ->whereIn('conversation_id', $conversationIds)
            ->whereNotNull('interview_id')
            ->where('user_id', '!=', auth()->id())
            ->where('created_at', '>=', now()->subDays(3))
            ->where('created_at', '<', now()->subMinutes(Message::RING_MINUTES))
            ->latest('id')
            ->limit(10)
            ->get()
            ->first(fn (Message $message) => $message->callState() === 'missed');

        if (! $missed) {
            return null;
        }

        return [
            'id' => $missed->id,
            'caller' => $missed->user?->name ?? 'Собеседник',
            'time' => $missed->created_at->format('d.m.Y H:i'),
            'chatUrl' => route('public.chats.show', $missed->conversation_id),
        ];
    }
}
