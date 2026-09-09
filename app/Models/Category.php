<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name' , 'description','slug'];

    public function employers()
    {
        return $this->hasMany(Employer::class);
    }

    /**
     * Вакансии категории — через компании, которые к ней относятся.
     */
    public function vacancies()
    {
        return $this->hasManyThrough(Vacancy::class, Employer::class);
    }
}
