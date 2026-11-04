<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /** Date filters are whole days in the school's time zone (App Flow E-12); timestamps are stored in UTC. */
    private const FILTER_TIMEZONE = 'Asia/Manila';

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'actor' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [], [
            'from' => 'start date',
            'to' => 'end date',
        ]);

        return Inertia::render('Admin/Audit', [
            'logs' => AuditLog::query()
                ->with('actor:id,first_name,middle_name,last_name')
                ->when($filters['actor'] ?? null, fn ($q, $actor) => $q->where('actor_id', $actor))
                ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
                ->when($request->string('entity_type')->toString(), fn ($q, $t) => $q->where('entity_type', $t))
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from, self::FILTER_TIMEZONE)->startOfDay()->utc()))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to, self::FILTER_TIMEZONE)->endOfDay()->utc()))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'filters' => $filters,
            'actors' => User::query()
                ->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
