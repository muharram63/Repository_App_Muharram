<?php

namespace App\Models\Trainer;

use Illuminate\Database\Eloquent\Model;

/** Пакет попыток с ценой в сомони — настраивается в админке. */
class TrainerPackage extends Model
{
    protected $table = 'trainer_packages';

    protected $fillable = ['title', 'attempts', 'price', 'active', 'position'];

    protected $casts = [
        'attempts' => 'integer',
        'price' => 'integer',
        'active' => 'boolean',
        'position' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('position')->orderBy('price');
    }
}
