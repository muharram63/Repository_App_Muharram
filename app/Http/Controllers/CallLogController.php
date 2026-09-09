<?php

namespace App\Http\Controllers;

use App\Support\CallJournal;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    /**
     * Журнал звонков кабинета: входящие, исходящие и пропущенные.
     * Сбор строк живёт в CallJournal — его же спрашивают живые счётчики.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        abort_if(! $user->employer && ! $user->applicant, 403);

        $rows = CallJournal::rows($user);
        $counts = CallJournal::counts($rows);

        $filter = in_array($request->query('filter'), ['in', 'out', 'missed'], true)
            ? $request->query('filter')
            : 'all';

        $visible = match ($filter) {
            'in' => $rows->where('direction', 'in'),
            'out' => $rows->where('direction', 'out'),
            'missed' => $rows->where('state', 'missed'),
            default => $rows,
        };

        return view($user->employer ? 'employer.pages.calls.index' : 'applicant.pages.calls.index', [
            'user' => $user,
            'rows' => $visible->values(),
            'counts' => $counts,
            'filter' => $filter,
        ]);
    }
}
