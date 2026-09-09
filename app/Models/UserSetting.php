<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $table = 'user_settings';

    protected $fillable = [
        'user_id',
        'call_sound',
        'call_notify',
        'mail_responses',
        'mail_messages',
        'hide_profile',
        'hide_contacts',
        'hide_company',
    ];

    protected $casts = [
        'call_sound' => 'boolean',
        'call_notify' => 'boolean',
        'mail_responses' => 'boolean',
        'mail_messages' => 'boolean',
        'hide_profile' => 'boolean',
        'hide_contacts' => 'boolean',
        'hide_company' => 'boolean',
    ];

    protected $attributes = [
        'call_sound' => true,
        'call_notify' => true,
        'mail_responses' => true,
        'mail_messages' => true,
        'hide_profile' => false,
        'hide_contacts' => false,
        'hide_company' => false,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
