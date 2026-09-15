<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminComment extends Model
{
    use SoftDeletes;

    protected $table = 'admin_comments';

    protected $fillable = [
        'user_id',
        'topic',
        'body',
        'status',
        'admin_id',
        'admin_reply',
        'answered_at',
        'seen_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * Тема обращения — даёт администратору фильтр, как reason у жалоб.
     */
    public const TOPICS = [
        'question' => 'Вопрос по работе площадки',
        'problem' => 'Что-то не работает',
        'abuse' => 'Сообщить о нарушении',
        'account' => 'Вопрос по аккаунту',
        'idea' => 'Предложение',
        'other' => 'Другое',
    ];

    public const STATUSES = [
        'new' => 'Новое',
        'in_review' => 'В работе',
        'answered' => 'Отвечено',
        'closed' => 'Закрыто',
    ];

    /**
     * Сколько обращений в сутки принимаем с одного аккаунта.
     */
    public const DAILY_LIMIT = 10;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function topicLabel(): string
    {
        return __(self::TOPICS[$this->topic] ?? $this->topic);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    /**
     * Ответ администратора написан. Это единственный признак «отвечено»:
     * статус ведёт очередь администратора и сам по себе ответом не является.
     */
    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    /**
     * Обращение всё ещё ждёт ответа: ответа нет и его не закрыли.
     */
    public function isWaiting(): bool
    {
        return ! $this->isAnswered() && $this->status !== 'closed';
    }

    /**
     * Подпись состояния для автора обращения — по факту ответа,
     * а не по служебному статусу очереди.
     */
    public function userStatusKey(): string
    {
        if ($this->isAnswered()) {
            return 'answered';
        }

        return $this->status === 'closed' ? 'closed' : $this->status;
    }

    public function userStatusLabel(): string
    {
        return __(self::STATUSES[$this->userStatusKey()] ?? $this->userStatusKey());
    }

    /**
     * Ответ уже есть, но автор его ещё не открывал.
     */
    public function isUnread(): bool
    {
        return $this->answered_at !== null && $this->seen_at === null;
    }

    /**
     * Пока ответа нет, автор может отозвать обращение.
     */
    public function canBeWithdrawn(): bool
    {
        return $this->answered_at === null && $this->status === 'new';
    }

    /**
     * Раздел обращений в кабинете — он у каждой роли свой.
     */
    public static function cabinetUrl(?User $user): string
    {
        return $user && $user->employer
            ? route('employer.support.index')
            : route('applicant.support.index');
    }
}
