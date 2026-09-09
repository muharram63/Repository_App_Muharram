<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Resume;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureFileController extends Controller
{
    /**
     * Вложение из переписки. Файлы лежат на приватном диске, поэтому
     * право на просмотр проверяется здесь, а не именем файла.
     */
    public function chatAttachment(Conversation $conversation, Message $message): StreamedResponse
    {
        $user = auth()->user();

        $isEmployer = $user->employer && $user->employer->id === $conversation->employer_id;
        $isApplicant = $user->applicant && $user->applicant->id === $conversation->applicant_id;

        abort_if(! $isEmployer && ! $isApplicant, 403);
        abort_if($message->conversation_id !== $conversation->id || ! $message->attachment_path, 404);

        return $this->serve(
            $this->relativePath($message->attachment_path),
            $message->attachment_name ?: 'file'
        );
    }

    /**
     * Документ резюме: владелец и админ — всегда, остальные —
     * пока анкета не скрыта из публичного каталога.
     */
    public function resumeDocument(Resume $resume): StreamedResponse
    {
        abort_if(! $resume->documents, 404);

        $owner = $resume->applicant?->user;
        $user = auth()->user();

        $privileged = $user->role === 'admin' || ($owner && $owner->id === $user->id);

        abort_if(
            ! $privileged && $owner && ($owner->setting('hide_profile') || $owner->isBlocked()),
            404
        );

        return $this->serve(
            $this->relativePath($resume->documents),
            basename($resume->documents)
        );
    }

    /**
     * Старые записи хранят путь с префиксом storage/ — приводим к пути на диске.
     */
    private function relativePath(string $path): string
    {
        return Str::after($path, 'storage/');
    }

    /**
     * Отдаём файл с приватного диска, а если он остался от прежней
     * публичной раскладки — с публичного.
     */
    private function serve(string $path, string $name): StreamedResponse
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, $name);
            }
        }

        abort(404);
    }
}
