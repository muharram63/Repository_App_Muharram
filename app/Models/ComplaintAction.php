<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintAction extends Model
{
    protected $table = 'complaint_actions';

    protected $fillable = [
        'complaint_id',
        'admin_id',
        'action',
        'comment',
    ];

    public const LABELS = [
        'status_new' => 'Вернул в новые',
        'status_in_review' => 'Взял в работу',
        'status_resolved' => 'Отметил решённой',
        'status_rejected' => 'Отклонил жалобу',
        'close_vacancy' => 'Снял вакансию с публикации',
        'hide_resume' => 'Скрыл резюме из каталога',
        'block_user' => 'Заблокировал пользователя',
        'reopen_vacancy' => 'Вернул вакансию в публикацию',
        'show_resume' => 'Вернул резюме в каталог',
        'unblock_user' => 'Разблокировал пользователя',
        'owner_new' => 'Владелец объекта вернул жалобу в новые',
        'owner_in_review' => 'Владелец объекта взял жалобу в работу',
        'owner_resolved' => 'Владелец объекта устранил замечание',
        'owner_rejected' => 'Владелец объекта отклонил жалобу',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function label(): string
    {
        return __(self::LABELS[$this->action] ?? $this->action);
    }
}
