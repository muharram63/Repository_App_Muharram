<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Вложения чата и документы резюме переезжают с публичного диска на приватный:
     * раньше их мог скачать любой, кто знал ссылку, мимо сессии и настроек приватности.
     */
    public function up(): void
    {
        DB::table('messages')->whereNotNull('attachment_path')->orderBy('id')
            ->each(function ($row) {
                // storage/ в начале остался от прежней раскладки
                $path = Str::after($row->attachment_path, 'storage/');

                $this->move($path, 'public', 'local');

                if ($row->attachment_path !== $path) {
                    DB::table('messages')->where('id', $row->id)->update(['attachment_path' => $path]);
                }
            });

        DB::table('resumes')->whereNotNull('documents')->orderBy('id')
            ->each(fn ($row) => $this->move(Str::after($row->documents, 'storage/'), 'public', 'local'));
    }

    public function down(): void
    {
        DB::table('messages')->whereNotNull('attachment_path')->orderBy('id')
            ->each(function ($row) {
                $path = Str::after($row->attachment_path, 'storage/');

                $this->move($path, 'local', 'public');

                DB::table('messages')->where('id', $row->id)
                    ->update(['attachment_path' => 'storage/'.$path]);
            });

        DB::table('resumes')->whereNotNull('documents')->orderBy('id')
            ->each(fn ($row) => $this->move(Str::after($row->documents, 'storage/'), 'local', 'public'));
    }

    /**
     * Перенос одного файла между дисками. Повторный запуск ничего не ломает.
     */
    private function move(string $path, string $from, string $to): void
    {
        if ($path === '' || ! Storage::disk($from)->exists($path)) {
            return;
        }

        if (! Storage::disk($to)->exists($path)) {
            Storage::disk($to)->writeStream($path, Storage::disk($from)->readStream($path));
        }

        Storage::disk($from)->delete($path);
    }
};
