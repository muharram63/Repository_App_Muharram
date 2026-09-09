<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Conversation;
use App\Models\Employer;
use App\Models\Message;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Список диалогов текущего пользователя.
     */
    public function index()
    {
        $user = auth()->user();

        $conversations = $this->conversationsQuery()
            ->with('employer.user', 'applicant.user', 'vacancy', 'lastMessage')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return view('public.pages.chats.index', [
            'user' => $user,
            'conversations' => $conversations,
            'isEmployer' => (bool) $user->employer,
        ]);
    }

    /**
     * Переписка.
     */
    public function show(Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);

        $conversation->load('employer.user', 'applicant.user', 'vacancy');

        // всё, что написал собеседник, помечаем прочитанным
        $conversation->messages()
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversations = $this->conversationsQuery()
            ->with('employer.user', 'applicant.user', 'lastMessage')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return view('public.pages.chats.show', [
            'user' => auth()->user(),
            'conversation' => $conversation,
            'conversations' => $conversations,
            'messages' => $conversation->messages()->with('user', 'replyTo.user', 'interview')->orderBy('id')->get(),
            'isEmployer' => auth()->user()->employer
                && auth()->user()->employer->id === $conversation->employer_id,
        ]);
    }

    /**
     * Начать (или открыть существующий) диалог.
     */
    public function start(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'applicant_id' => 'nullable|exists:applicants,id',
            'employer_id' => 'nullable|exists:employers,id',
            'vacancy_id' => 'nullable|exists:vacancies,id',
        ]);

        if ($user->role === 'employer') {
            $employer = $user->employer;
            if (! $employer) {
                return redirect()->route('public.employers.create')
                    ->with('error', 'Сначала заполните анкету работодателя.');
            }

            $applicantId = $validated['applicant_id'] ?? null;
            if (! $applicantId) {
                return back()->with('error', 'Не выбран соискатель.');
            }

            $employerId = $employer->id;
        } elseif ($user->role === 'applicant') {
            $applicant = $user->applicant;
            if (! $applicant) {
                return redirect()->route('public.applicants.create')
                    ->with('error', 'Сначала заполните анкету соискателя.');
            }

            $employerId = $validated['employer_id'] ?? null;
            if (! $employerId) {
                return back()->with('error', 'Не выбрана компания.');
            }

            $applicantId = $applicant->id;
        } else {
            return back()->with('error', 'Чат доступен работодателям и соискателям.');
        }

        $conversation = Conversation::where('employer_id', $employerId)
            ->where('applicant_id', $applicantId)
            ->first();

        if (! $conversation) {
            // новый диалог заводим только между связанными сторонами,
            // иначе чат превращается в канал для рассылки незнакомым людям
            $company = Employer::find($employerId);

            if (! $company || ! $company->isRelatedToApplicant((int) $applicantId)) {
                return back()->with('error', 'Написать можно после отклика или приглашения.');
            }

            $conversation = Conversation::create([
                'employer_id' => $employerId,
                'applicant_id' => $applicantId,
                'vacancy_id' => $validated['vacancy_id'] ?? null,
            ]);
        }

        return redirect()->route('public.chats.show', $conversation);
    }

    /**
     * Отправка сообщения.
     */
    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);

        $validated = $request->validate([
            'body' => 'nullable|string|max:2000',
            'reply_to_id' => 'nullable|exists:messages,id',
            'attachment' => 'nullable|file|max:10240|mimes:jpeg,jpg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,zip',
        ], [], ['attachment' => 'файл']);

        // пустое сообщение без файла отправлять нечего
        if (blank($validated['body'] ?? null) && ! $request->hasFile('attachment')) {
            return back()->with('error', 'Напишите сообщение или приложите файл.');
        }

        // отвечать можно только на сообщение из этого же диалога
        $replyToId = $validated['reply_to_id'] ?? null;
        if ($replyToId && ! $conversation->messages()->whereKey($replyToId)->exists()) {
            $replyToId = null;
        }

        $attachment = [];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachment = [
                // приватный диск: файл отдаётся только через SecureFileController
                'attachment_path' => $file->store('chat/'.$conversation->id, 'local'),
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_size' => $file->getSize(),
                'attachment_mime' => $file->getMimeType(),
            ];
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'reply_to_id' => $replyToId,
            'body' => $validated['body'] ?? null,
        ] + $attachment);

        $conversation->update(['last_message_at' => now()]);

        // собеседнику — запись в ящик уведомлений
        $conversation->loadMissing('employer', 'applicant');
        $recipientId = $conversation->employer?->user_id === auth()->id()
            ? $conversation->applicant?->user_id
            : $conversation->employer?->user_id;

        $chatUrl = route('public.chats.show', $conversation);

        UserNotification::deliver(
            $recipientId,
            'message',
            'Новое сообщение',
            auth()->user()->name.': '.$message->previewText(),
            $chatUrl,
            'mail_messages',
            collapse: true,
        );

        // отправитель тоже видит переписку в ящике — одной строкой на диалог
        // и уже прочитанной, чтобы счётчик не рос от собственных сообщений
        UserNotification::deliver(
            auth()->id(),
            'message',
            'Вы написали в чате',
            $message->previewText(),
            $chatUrl,
            'mail_messages',
            collapse: true,
            read: true,
        );

        return redirect()->route('public.chats.show', $conversation);
    }

    /**
     * Видеозвонок из чата. Начать может любой участник диалога.
     */
    public function startCall(Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);

        // если звонок уже идёт — присоединяемся к нему, а не плодим комнаты
        $active = \App\Models\Interview::where('employer_id', $conversation->employer_id)
            ->where('applicant_id', $conversation->applicant_id)
            ->whereNotIn('status', ['canceled', 'declined'])
            ->latest('scheduled_at')
            ->get()
            ->first(fn (\App\Models\Interview $interview) => $interview->isRoomOpen());

        $isNew = false;

        if (! $active) {
            $isNew = true;
            $active = \App\Models\Interview::create([
                'employer_id' => $conversation->employer_id,
                'applicant_id' => $conversation->applicant_id,
                'vacancy_id' => $conversation->vacancy_id,
                'scheduled_at' => now(),
                'duration_minutes' => 30,
                'room' => 'workio-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(24)),
                'note' => 'Звонок из чата',
                'status' => 'confirmed',
            ]);

        }

        // приглашение в переписке: обновляем, если прошлое уже «остыло»,
        // иначе повторное нажатие кнопки засоряло бы чат
        // ограничение считаем по своим приглашениям: ответный звонок
        // собеседника должен создать уведомление, а не молчать
        $lastInvite = $conversation->messages()
            ->where('interview_id', $active->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        if ($isNew || ! $lastInvite || $lastInvite->created_at->lt(now()->subSeconds(10))) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'interview_id' => $active->id,
                'body' => null,
            ]);

            $conversation->update(['last_message_at' => now()]);
        }

        return redirect()->route('public.interviews.show', $active);
    }

    /**
     * Редактирование собственного сообщения.
     */
    public function updateMessage(Request $request, Conversation $conversation, Message $message)
    {
        $this->authorizeParticipant($conversation);

        // править можно только своё сообщение и только из этого диалога
        abort_if($message->conversation_id !== $conversation->id || $message->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'body' => $message->attachment_path ? 'nullable|string|max:2000' : 'required|string|max:2000',
        ]);

        if (trim((string) ($validated['body'] ?? '')) !== (string) $message->body) {
            $message->update([
                'body' => $validated['body'] ?? null,
                'edited_at' => now(),
            ]);
        }

        return back()->with('status', 'Сообщение изменено.');
    }

    /**
     * Удаление собственного сообщения.
     */
    public function destroyMessage(Conversation $conversation, Message $message)
    {
        $this->authorizeParticipant($conversation);

        // удалять можно только своё сообщение и только из этого диалога
        abort_if($message->conversation_id !== $conversation->id || $message->user_id !== auth()->id(), 403);

        // файл вложения нужен только вместе с сообщением
        if ($message->attachment_path) {
            // storage/ в начале остался от прежней публичной раскладки
            $path = \Illuminate\Support\Str::after($message->attachment_path, 'storage/');

            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
        }

        $message->delete();

        // дата последнего сообщения могла измениться
        $last = $conversation->messages()->latest('id')->first();
        $conversation->update(['last_message_at' => $last?->created_at]);

        return back()->with('status', 'Сообщение удалено.');
    }

    /**
     * Новые сообщения для автообновления ленты (без перезагрузки страницы).
     */
    public function poll(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);

        $afterId = (int) $request->query('after', 0);
        $since = $request->query('since');

        $messages = $conversation->messages()
            ->with('user', 'replyTo.user', 'interview')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get();

        // сообщения, отредактированные после прошлого опроса
        $edited = $since
            ? $conversation->messages()
                ->where('id', '<=', $afterId)
                ->whereNotNull('edited_at')
                ->where('edited_at', '>', \Illuminate\Support\Carbon::createFromTimestamp((int) $since))
                ->get()
                ->map(fn (Message $m) => ['id' => $m->id, 'body' => $m->body])
                ->values()
            : collect();

        $conversation->messages()
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // свои сообщения, которые собеседник успел прочитать
        $readQuery = $conversation->messages()
            ->where('user_id', auth()->id())
            ->whereNotNull('read_at');

        if ($since) {
            $readQuery->where('read_at', '>', \Illuminate\Support\Carbon::createFromTimestamp((int) $since));
        }

        $read = $readQuery->pluck('id');

        // id удалённых сообщений — собеседник уберёт их из открытой ленты.
        // Отдаём только удалённые с прошлого опроса: иначе список растёт без предела
        $deleted = $conversation->messages()
            ->onlyTrashed()
            ->when($since, fn ($query) => $query->where(
                'deleted_at',
                '>',
                \Illuminate\Support\Carbon::createFromTimestamp((int) $since)
            ))
            ->pluck('id');

        return response()->json([
            'messages' => $messages->map(fn (Message $message) => [
                'id' => $message->id,
                'mine' => $message->user_id === auth()->id(),
                'author' => $message->user?->name ?? 'Пользователь',
                'body' => $message->body,
                'time' => $message->created_at->format('d.m.Y H:i'),
                'edited' => (bool) $message->edited_at,
                'read' => (bool) $message->read_at,
                'call' => $message->interview ? [
                    'url' => route('public.interviews.show', $message->interview),
                    'open' => $message->interview->isRoomOpen(),
                    'title' => $message->callTitle($message->user_id === auth()->id()),
                    'note' => $message->callNote($message->user_id === auth()->id()),
                    'missed' => $message->callState() === 'missed',
                ] : null,
                'file' => $message->attachment_path ? [
                    'url' => route('public.files.chat', [$conversation, $message]),
                    'name' => $message->attachment_name,
                    'size' => $message->attachmentSizeLabel(),
                    'image' => $message->attachmentIsImage(),
                ] : null,
                'reply' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'author' => $message->replyTo->user?->name ?? 'Пользователь',
                    'body' => $message->replyTo->body
                        ? \Illuminate\Support\Str::limit($message->replyTo->body, 90)
                        : 'Вложение',
                ] : null,
                'deleteUrl' => $message->user_id === auth()->id()
                    ? route('public.chats.message.destroy', [$conversation, $message])
                    : null,
            ]),
            'edited' => $edited,
            'read' => $read,
            'deleted' => $deleted,
            'now' => now()->timestamp,
        ]);
    }

    /**
     * Диалоги, доступные текущему пользователю.
     */
    private function conversationsQuery()
    {
        $user = auth()->user();
        $query = Conversation::query();

        if ($user->employer) {
            return $query->where('employer_id', $user->employer->id);
        }

        if ($user->applicant) {
            return $query->where('applicant_id', $user->applicant->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function authorizeParticipant(Conversation $conversation): void
    {
        $user = auth()->user();

        $isEmployer = $user->employer && $user->employer->id === $conversation->employer_id;
        $isApplicant = $user->applicant && $user->applicant->id === $conversation->applicant_id;

        abort_if(! $isEmployer && ! $isApplicant, 403);
    }
}
