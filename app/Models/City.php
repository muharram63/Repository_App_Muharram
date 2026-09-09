<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = ['country' , 'region'];

    public function vacancies()
    {
        return $this->hasMany(Vacancy::class);
    }
}
