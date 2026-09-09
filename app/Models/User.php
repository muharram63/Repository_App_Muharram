<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{


    /**
     * role и status намеренно вне массового заполнения: их назначают
     * явным присваиванием в регистрации и в админке.
     */
    protected $fillable = [
      'name' ,
      'email' ,
      'avatar' ,
      'password' ,
      'locale' ,
      'theme' ,

    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_register' => 'date',
            'password' => 'hashed',
        ];
    }




    /**
     * Аватар считается заданным, только если файл реально лежит на диске:
     * ссылка на удалённый файл даёт битую картинку вместо буквы-заглушки.
     */
    /**
     * Инициалы для кружка-заглушки: «Далер Рахимов» -> «ДР».
     */
    public function initials(int $count = 1): string
    {
        $words = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';

        foreach (array_slice($words, 0, $count) as $word) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }

    public function hasAvatar(): bool
    {
        return ! empty($this->avatar) && is_file(public_path($this->avatar));
    }

    public function applicant()
    {
        return $this->hasOne(Applicant::class);
    }


    public function employer()
    {
        return $this->hasOne(Employer::class);
    }

    /**
     * Дата регистрации — день создания аккаунта.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->date_register = $user->date_register ?: now()->toDateString();
        });
    }

    /**
     * Аккаунт закрыт для входа и для работы в уже открытой сессии.
     */
    /**
     * Статусы, при которых аккаунт закрыт: вход запрещён,
     * а вакансии, резюме и страница компании убраны из публичной части.
     * inactive оставлен ради старых записей — вручную он больше не ставится.
     */
    public const BLOCKED_STATUSES = ['blocked', 'inactive'];

    public function isBlocked(): bool
    {
        return in_array($this->status, self::BLOCKED_STATUSES, true);
    }

    /**
     * Только те, кому не закрыт доступ. Используется публичными каталогами.
     */
    public function scopeActiveAccount($query)
    {
        return $query->whereNotIn('status', self::BLOCKED_STATUSES);
    }

    /**
     * Сообщение о блокировке: причину показываем, автора жалобы — нет.
     */
    public function blockedMessage(): string
    {
        $notice = ModerationNotice::where('user_id', $this->id)
            ->where('action', 'block_user')
            ->latest('id')
            ->first();

        if (! $notice) {
            return 'Аккаунт заблокирован. Обратитесь в поддержку.';
        }

        return 'Аккаунт заблокирован модератором'
            .($notice->reason ? ' по причине «'.$notice->reason.'»' : '')
            .'. Обратитесь в поддержку, если считаете блокировку ошибкой.';
    }

    /**
     * Ящик уведомлений кабинета.
     */
    public function notificationsInbox()
    {
        return $this->hasMany(UserNotification::class)->latest('id');
    }

    public function unreadNotificationsCount(): int
    {
        return $this->cabinetBadges()['notifications'];
    }

    /** @var array<string,int>|null */
    private ?array $cabinetBadgesCache = null;

    /**
     * Цифры на пунктах меню кабинета. Считаются один раз за запрос:
     * сайдбар и колокольчик рисуются на каждой странице и раньше
     * повторяли одни и те же запросы.
     */
    public function cabinetBadges(): array
    {
        if ($this->cabinetBadgesCache !== null) {
            return $this->cabinetBadgesCache;
        }

        $freshDecisions = Complaint::where('user_id', $this->id)
            ->whereNull('seen_at')
            ->where(fn ($q) => $q->whereIn('status', ['resolved', 'rejected'])->orWhereNotNull('admin_comment'))
            ->count();

        return $this->cabinetBadgesCache = [
            'notifications' => UserNotification::where('user_id', $this->id)->whereNull('read_at')->count(),
            'support' => AdminComment::where('user_id', $this->id)
                ->whereNotNull('answered_at')->whereNull('seen_at')->count(),
            'complaints' => $freshDecisions + Complaint::againstUser($this)
                ->whereIn('status', ['new', 'in_review'])->count(),
        ];
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * Настройки пользователя со значениями по умолчанию,
     * даже если строку ещё не создавали.
     */
    public function settingsOrDefault(): UserSetting
    {
        return $this->settings ?: new UserSetting(['user_id' => $this->id]);
    }

    /**
     * Одна настройка: пригодится в шаблонах и выборках.
     */
    public function setting(string $key): bool
    {
        return (bool) $this->settingsOrDefault()->{$key};
    }


}
