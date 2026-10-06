<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Ссылки в уже отправленных уведомлениях — без адреса сайта.
 *
 * Уведомления, созданные планировщиком или очередью, хранили адрес из
 * APP_URL («http://localhost/orders/5»), и при сайте на другом адресе такая
 * ссылка не открывалась. Новые уведомления пишутся уже путём от корня
 * (UserNotification::localUrl); здесь — те, что были до этого.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_notifications')
            ->where('url', 'like', 'http%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $local = App\Models\UserNotification::localUrl($row->url);

                    if ($local !== $row->url) {
                        DB::table('user_notifications')->where('id', $row->id)->update(['url' => $local]);
                    }
                }
            });
    }

    public function down(): void
    {
    }
};
