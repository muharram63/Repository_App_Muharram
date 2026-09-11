<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employer extends Model
{

    protected $table = 'employers';
    protected $fillable = [
      'logo',
      'user_id',
        'company_name',
        'job',
        'email_company',
        'phone',
        'category_id',
        'description',
        'city_id',
        'industry_id',
        'website_url',

    ];

    /**
     * Инициалы компании без правовой формы и кавычек:
     * «ИП «Ориён Групп 127»» -> «ОГ».
     */
    public function initials(int $count = 2): string
    {
        $name = (string) $this->company_name;

        // убираем правовую форму в начале названия
        $name = preg_replace('/^\s*(ООО|ОАО|ЗАО|ПАО|ИП|ТОО|АО|ЧП|LLC|LTD|Группа компаний)\s*/ui', '', $name);
        // и кавычки любых видов
        $name = trim(preg_replace('/[«»"\'`]/u', ' ', $name));

        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';

        foreach ($words as $word) {
            if (preg_match('/^\d+$/u', $word)) {
                continue; // порядковые номера в названии пропускаем
            }
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
            if (mb_strlen($letters) >= $count) {
                break;
            }
        }

        return $letters !== '' ? $letters : '?';
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }
    public function vacancies()
    {
        return $this->hasMany(Vacancy::class);
    }

    public function resumeResponses()
    {
        return $this->hasMany(ResumeResponse::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Соискатель связан с компанией: откликался на её вакансию,
     * получал приглашение на резюме или уже переписывается с ней.
     * По этому признаку разрешаем писать в чат и назначать собеседования.
     */
    public function isRelatedToApplicant(int $applicantId): bool
    {
        $responded = VacancyResponse::where('applicant_id', $applicantId)
            ->whereIn('vacancy_id', $this->vacancies()->select('id'))
            ->exists();

        if ($responded) {
            return true;
        }

        $invited = ResumeResponse::where('employer_id', $this->id)
            ->whereIn('resume_id', Resume::where('applicant_id', $applicantId)->select('id'))
            ->exists();

        if ($invited) {
            return true;
        }

        return Conversation::where('employer_id', $this->id)
            ->where('applicant_id', $applicantId)
            ->exists();
    }

}
