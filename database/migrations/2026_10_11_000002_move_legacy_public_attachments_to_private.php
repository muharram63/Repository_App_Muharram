<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
 * Старые вложения чатов и документы резюме — из открытой папки в закрытую.
 *
 * Раньше эти файлы сохранялись на диск public (storage/app/public), и всё,
 * что там лежит, отдаётся по прямой ссылке без входа: /storage/chat/2/….txt.
 * Новые файлы давно пишутся на закрытый диск local, а SecureFileController
 * отдаёт их с проверкой прав — сначала с local, потом с public. Поэтому
 * перенос по тому же пути ничего не ломает: ссылки в сообщениях и резюме
 * продолжают работать, только уже через проверку прав.
 *
 * Если на закрытом диске уже есть такой же файл, открытая копия удаляется;
 * если есть, но другой — открытая копия остаётся, а в лог пишется
 * предупреждение: решать, какая верная, должен человек.
 */
return new class extends Migration
{
    private const FOLDERS = ['chat', 'resumes'];

    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        foreach (self::FOLDERS as $folder) {
            foreach ($public->allFiles($folder) as $path) {
                if (! $private->exists($path)) {
                    $private->put($path, $public->get($path));
                }

                if ($private->get($path) === $public->get($path)) {
                    $public->delete($path);
                } else {
                    Log::warning('Вложение есть и в открытой, и в закрытой папке, но разное — открытая копия оставлена', ['path' => $path]);
                }
            }
        }
    }

    /** Обратно в открытую папку файлы не возвращаем — это и была дыра. */
    public function down(): void
    {
    }
};
