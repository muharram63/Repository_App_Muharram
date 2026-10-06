<?php

namespace App\Http\Controllers;

use App\Models\Applicant;

/**
 * Публичный профиль мастера — портфолио вместо резюме.
 *
 * Сантехнику или маляру резюме часто нечем заполнить, а показать сделанное
 * есть что. Эта страница — витрина таких работ; резюме, если оно есть,
 * доступно отсюда же ссылкой.
 */
class PublicMasterController extends Controller
{
    public function show(Applicant $applicant)
    {
        $owner = $applicant->user;
        $viewer = auth()->user();
        $privileged = $viewer && ($viewer->role === 'admin' || ($owner && $viewer->id === $owner->id));

        // скрытый профиль и закрытый аккаунт — как у резюме: только владельцу и администратору
        abort_if(! $owner, 404);
        abort_if(! $privileged && ($owner->setting('hide_profile') || $owner->isBlocked()), 404);

        $works = $applicant->portfolioWorks()->with(['media', 'category'])->get();

        // пустая витрина посторонним не нужна; владельцу — подсказка, как её заполнить
        abort_if($works->isEmpty() && ! $privileged, 404);

        return view('public.pages.masters.show', [
            'applicant' => $applicant,
            'owner' => $owner,
            'works' => $works,
            'resumes' => $applicant->resume()->latest()->get(['id', 'profession']),
            'isOwner' => $viewer && $viewer->id === $owner->id,
            'hideContacts' => $owner->setting('hide_contacts') && ! $privileged,
            'categories' => $works->pluck('category')->filter()->unique('id')->values(),
        ]);
    }
}
