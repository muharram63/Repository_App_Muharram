<?php

namespace App\Http\Controllers;

use App\Models\ModerationNotice;
use Illuminate\Http\Request;

class ModerationNoticeController extends Controller
{
    /**
     * Пользователь прочитал уведомления модератора — только свои.
     */
    public function seen(Request $request)
    {
        ModerationNotice::where('user_id', $request->user()->id)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return back();
    }
}
