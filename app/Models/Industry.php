<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    protected $fillable = [
        'name' ,
        'category_id',
        'parent_id',
        'description',
        ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
