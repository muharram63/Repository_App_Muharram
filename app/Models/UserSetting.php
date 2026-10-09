<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $table = 'user_settings';

    /**
     * Уведомления работодателю о кандидатах: виды и каналы.
     * Все включены по умолчанию — выключаются в настройках кабинета.
     */
    public const EMPLOYER_ALERTS = ['notify_new_candidates', 'notify_reports', 'notify_reminders'];

    public const ALERT_CHANNELS = ['channel_site', 'channel_browser', 'channel_email'];

    protected $fillable = [
        'user_id',
        'call_sound',
        'call_notify',
        'mail_responses',
        'mail_messages',
        'hide_profile',
        'hide_contacts',
        'hide_company',
        'notify_new_candidates',
        'notify_reports',
        'notify_reminders',
        'channel_site',
        'channel_browser',
        'channel_email',
    ];

    protected $casts = [
        'call_sound' => 'boolean',
        'call_notify' => 'boolean',
        'mail_responses' => 'boolean',
        'mail_messages' => 'boolean',
        'hide_profile' => 'boolean',
        'hide_contacts' => 'boolean',
        'hide_company' => 'boolean',
        'notify_new_candidates' => 'boolean',
        'notify_reports' => 'boolean',
        'notify_reminders' => 'boolean',
        'channel_site' => 'boolean',
        'channel_browser' => 'boolean',
        'channel_email' => 'boolean',
    ];

    protected $attributes = [
        'call_sound' => true,
        'call_notify' => true,
        'mail_responses' => true,
        'mail_messages' => true,
        'hide_profile' => false,
        'hide_contacts' => false,
        'hide_company' => false,
        'notify_new_candidates' => true,
        'notify_reports' => true,
        'notify_reminders' => true,
        'channel_site' => true,
        'channel_browser' => true,
        'channel_email' => true,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
