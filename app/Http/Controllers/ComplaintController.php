<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    /**
     * Жалоба от пользователя на вакансию, резюме, компанию или пользователя.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_type' => 'required|in:vacancy,resume,company,user',
            'target_id' => 'required|integer|min:1',
            'reason' => 'required|in:'.implode(',', array_keys(Complaint::REASONS)),
            'message' => 'nullable|string|max:1000',
        ]);

        // не больше 10 жалоб в сутки с одного аккаунта
        // withTrashed: отозванные жалобы тоже занимают место в суточном лимите
        $lastDay = Complaint::withTrashed()
            ->where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($lastDay >= 10) {
            return back()->with('error', 'Вы отправили слишком много жалоб за сутки. Попробуйте завтра.');
        }

        $ownerId = Complaint::ownerId($validated['target_type'], (int) $validated['target_id']);

        // объекта нет — жаловаться не на что
        if ($ownerId === null) {
            return back()->with('error', 'Объект жалобы не найден.');
        }

        // на своё имущество и на самого себя жалоб не принимаем
        if ($ownerId === $request->user()->id) {
            return back()->with('error', 'Нельзя пожаловаться на собственный объект.');
        }

        $exists = Complaint::where('user_id', $request->user()->id)
            ->where('target_type', $validated['target_type'])
            ->where('target_id', $validated['target_id'])
            ->whereIn('status', ['new', 'in_review'])
            ->exists();

        if ($exists) {
            return back()->with('status', 'Вы уже отправляли жалобу на этот объект — она в работе.');
        }

        $complaint = Complaint::create($validated + ['user_id' => $request->user()->id]);

        // владелец объекта узнаёт о жалобе сразу, но не о том, кто её отправил
        \App\Models\ModerationNotice::create([
            'user_id' => $ownerId,
            'complaint_id' => $complaint->id,
            'action' => 'complaint_received',
            'subject' => $complaint->targetTitle(),
            'reason' => $complaint->reasonLabel(),
            'comment' => 'Проверьте объект. Когда устраните замечание, отметьте жалобу исправленной.',
        ]);

        UserNotification::deliver(
            $ownerId,
            'complaint',
            'На ваш объект пожаловались',
            'Причина: '.$complaint->reasonLabel().'. Отметьте замечание исправленным в разделе «Жалобы».',
            Complaint::cabinetUrl(User::find($ownerId)),
        );

        return back()->with('status', 'Жалоба отправлена. Владелец объекта получил уведомление.');
    }

    /**
     * Владелец объекта сам ведёт жалобу: берёт в работу, отмечает
     * устранённой или отклоняет, если нарушения нет. Модератор статус не трогает.
     */
    public function updateStatus(Request $request, Complaint $complaint)
    {
        abort_if($complaint->ownerUserId() !== $request->user()->id, 403);

        if (! $complaint->ownerCanSetStatus()) {
            return back()->with('error', 'Жалобу на вас как на пользователя рассматривает модератор.');
        }

        $validated = $request->validate([
            'status' => 'required|in:new,in_review,resolved,rejected',
            'reply' => 'nullable|string|max:500',
        ]);

        $status = $validated['status'];
        $closing = in_array($status, ['resolved', 'rejected'], true);

        $complaint->update([
            'status' => $status,
            'resolved_at' => $closing ? now() : null,
            // автор жалобы должен увидеть новый исход как непрочитанный
            'seen_at' => null,
            'owner_reply' => $validated['reply'] ?? $complaint->owner_reply,
        ]);

        \App\Models\ComplaintAction::create([
            'complaint_id' => $complaint->id,
            'admin_id' => $request->user()->id,
            'action' => 'owner_'.$status,
            'comment' => $validated['reply'] ?? null,
        ]);

        UserNotification::deliver(
            $complaint->user_id,
            'complaint_status',
            'Ответ по вашей жалобе',
            $complaint->targetTitle().': '.$complaint->statusLabel(),
            Complaint::cabinetUrl(User::find($complaint->user_id)),
        );

        $messages = [
            'new' => 'Жалоба возвращена в новые.',
            'in_review' => 'Жалоба взята в работу.',
            'resolved' => 'Замечание отмечено исправленным.',
            'rejected' => 'Жалоба отклонена.',
        ];

        return back()->with('status', $messages[$status]);
    }

    /**
     * Автор может отозвать жалобу, пока её не взяли в работу.
     */
    public function destroy(Request $request, Complaint $complaint)
    {
        abort_if($complaint->user_id !== $request->user()->id, 403);

        if ($complaint->status !== 'new') {
            return back()->with('error', 'Жалоба уже в работе — отозвать её нельзя.');
        }

        $complaint->delete();

        return back()->with('status', 'Жалоба отозвана.');
    }
}
