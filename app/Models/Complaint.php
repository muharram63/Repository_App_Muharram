<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'target_type',
        'target_id',
        'reason',
        'message',
        'status',
        'admin_comment',
        'owner_reply',
        'resolved_at',
        'seen_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    public const REASONS = [
        'fraud' => 'Похоже на мошенничество',
        'spam' => 'Спам или реклама',
        'fake' => 'Недостоверные данные',
        'offensive' => 'Оскорбительный контент',
        'discrimination' => 'Дискриминация в требованиях',
        'other' => 'Другое',
    ];

    public const STATUSES = [
        'new' => 'Новая',
        'in_review' => 'В работе',
        'resolved' => 'Решена',
        'rejected' => 'Отклонена',
    ];

    public const TARGETS = [
        'vacancy' => 'Вакансия',
        'resume' => 'Резюме',
        'company' => 'Компания',
        'user' => 'Пользователь',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actions()
    {
        return $this->hasMany(ComplaintAction::class)->latest("id");
    }

    public function reasonLabel(): string
    {
        return __(self::REASONS[$this->reason] ?? $this->reason);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    /**
     * Меры, которые сейчас имеют смысл для этой жалобы.
     * Один и тот же список используют шаблон и проверка в act().
     */
    public function allowedActions(): array
    {
        $target = $this->target();

        if (! $target) {
            return [];
        }

        // Права администратора: смотреть, блокировать и удалять.
        // Содержимое пользователей он не правит — ни снять вакансию,
        // ни скрыть резюме нельзя, это дело владельца объекта.
        $actions = [];

        $owner = $this->targetOwner();

        // администратора не блокируем, себя тоже
        if ($owner && $owner->role !== 'admin' && $owner->id !== auth()->id()) {
            $actions[] = $owner->status === 'blocked' ? 'unblock_user' : 'block_user';
        }

        return $actions;
    }

    /**
     * Владелец объекта жалобы как модель.
     */
    public function targetOwner(): ?User
    {
        $target = $this->target();

        if (! $target) {
            return null;
        }

        return match ($this->target_type) {
            'user' => $target,
            'company' => $target->user,
            'vacancy' => $target->employer?->user,
            'resume' => $target->applicant?->user,
            default => null,
        };
    }

    public function targetLabel(): string
    {
        return __(self::TARGETS[$this->target_type] ?? $this->target_type);
    }

    /**
     * Сам объект жалобы — вакансия, резюме, компания или пользователь.
     */
    /**
     * Загрузить объекты сразу для всей выборки — иначе запрос на каждую строку.
     */
    public static function preloadTargets($complaints): void
    {
        $byType = collect($complaints)->groupBy("target_type");

        $loaders = [
            "vacancy" => fn ($ids) => Vacancy::with("employer.user")->findMany($ids)->keyBy("id"),
            "resume" => fn ($ids) => Resume::with("applicant.user")->findMany($ids)->keyBy("id"),
            "company" => fn ($ids) => Employer::with("user")->findMany($ids)->keyBy("id"),
            "user" => fn ($ids) => User::findMany($ids)->keyBy("id"),
        ];

        foreach ($byType as $type => $items) {
            if (! isset($loaders[$type])) {
                continue;
            }

            $found = $loaders[$type](collect($items)->pluck("target_id")->unique()->all());

            foreach ($items as $complaint) {
                $complaint->setTarget($found[$complaint->target_id] ?? null);
            }
        }
    }

    /**
     * Объект жалобы кэшируется на время запроса. При перечитывании модели
     * кэш надо сбросить, иначе после смены состояния объекта
     * allowedActions() отвечал бы по старым данным.
     */
    public function refresh()
    {
        $this->targetCache = null;
        $this->targetResolved = false;

        return parent::refresh();
    }

    public function setTarget($target): void
    {
        $this->targetCache = $target;
        $this->targetResolved = true;
    }

    private mixed $targetCache = null;

    private bool $targetResolved = false;

    public function target()
    {
        // объект жалобы дёргается несколько раз за рендер — держим его в памяти
        if ($this->targetResolved) {
            return $this->targetCache;
        }

        $this->targetResolved = true;

        $this->targetCache = match ($this->target_type) {
            'vacancy' => Vacancy::with('employer.user')->find($this->target_id),
            'resume' => Resume::with('applicant.user')->find($this->target_id),
            'company' => Employer::with('user')->find($this->target_id),
            'user' => User::find($this->target_id),
            default => null,
        };

        return $this->targetCache;
    }

    /**
     * Название объекта для списка.
     */
    public function targetTitle(): string
    {
        $target = $this->target();

        return match (true) {
            $target === null => 'Объект удалён',
            $this->target_type === 'vacancy' => $target->title,
            $this->target_type === 'resume' => $target->profession.' — '.($target->applicant?->user?->name ?? '—'),
            $this->target_type === 'company' => $target->company_name,
            default => $target->name,
        };
    }

    /**
     * Ссылка на объект в админке.
     */
    public function targetUrl(): ?string
    {
        $target = $this->target();

        if (! $target) {
            return null;
        }

        return match ($this->target_type) {
            'vacancy' => route('superadmin.vacancy.show', $target),
            'resume' => route('superadmin.resumes.show', $target),
            'company' => route('superadmin.companies.show', $target),
            'user' => route('superadmin.users.edit', $target),
            default => null,
        };
    }

    /**
     * Ссылка на объект в публичной части — для автора жалобы.
     */
    public function publicUrl(): ?string
    {
        $target = $this->target();

        if (! $target) {
            return null;
        }

        return match ($this->target_type) {
            'vacancy' => route('public.vacancies.show', $target),
            'resume' => route('public.resumes.show', $target),
            'company' => route('public.companies.show', $target),
            default => null,
        };
    }

    /**
     * Владелец объекта жалобы — на свой объект жаловаться нельзя.
     */
    public static function ownerId(string $type, int $id): ?int
    {
        return match ($type) {
            'vacancy' => Vacancy::with('employer')->find($id)?->employer?->user_id,
            'resume' => Resume::with('applicant')->find($id)?->applicant?->user_id,
            'company' => Employer::find($id)?->user_id,
            'user' => User::find($id)?->id,
            default => null,
        };
    }

    /**
     * Владелец объекта этой жалобы.
     */
    public function ownerUserId(): ?int
    {
        return self::ownerId($this->target_type, (int) $this->target_id);
    }

    /**
     * Жалобы на объекты этого пользователя: на него самого, на его компанию,
     * на её вакансии и на его резюме.
     */
    public static function againstUser(User $user)
    {
        $employer = $user->employer;
        $applicant = $user->applicant;

        // подзапросы вместо pluck: один поход в базу вместо трёх,
        // и никакого IN(...) на сотни идентификаторов
        return self::query()->where(function ($query) use ($user, $employer, $applicant) {
            $query->where(fn ($q) => $q->where('target_type', 'user')->where('target_id', $user->id));

            if ($employer) {
                $query->orWhere(fn ($q) => $q->where('target_type', 'company')->where('target_id', $employer->id));

                $query->orWhere(fn ($q) => $q->where('target_type', 'vacancy')->whereIn(
                    'target_id',
                    Vacancy::where('employer_id', $employer->id)->select('id')
                ));
            }

            if ($applicant) {
                $query->orWhere(fn ($q) => $q->where('target_type', 'resume')->whereIn(
                    'target_id',
                    Resume::where('applicant_id', $applicant->id)->select('id')
                ));
            }
        });
    }

    /**
     * Раздел «Жалобы» в кабинете пользователя — он у каждой роли свой.
     */
    public static function cabinetUrl(?User $user): string
    {
        return $user && $user->employer
            ? route('employer.complaints.index')
            : route('applicant.complaints.index');
    }

    /**
     * Владелец объекта ведёт статус жалобы сам — но только когда объект
     * можно поправить. Жалобу на себя как на человека закрыть нельзя:
     * иначе нарушитель одним нажатием гасит любое обращение о себе.
     */
    public function ownerCanSetStatus(): bool
    {
        return $this->target_type !== 'user';
    }

    /**
     * Замечание ещё не устранено — показываем красным с восклицательным знаком.
     */
    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved', 'rejected'], true);
    }

    /**
     * Решение принято, но автор его ещё не открывал.
     */
    public function isUnread(): bool
    {
        if ($this->seen_at !== null) {
            return false;
        }

        // новость для автора — это либо вердикт владельца объекта,
        // либо мера модератора, о которой он написал в комментарии
        return in_array($this->status, ['resolved', 'rejected'], true)
            || $this->admin_comment !== null;
    }
}
