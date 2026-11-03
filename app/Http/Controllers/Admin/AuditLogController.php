<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Audit', [
            'logs' => AuditLog::query()
                ->with('actor:id,first_name,middle_name,last_name')
                ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
                ->when($request->string('entity_type')->toString(), fn ($q, $t) => $q->where('entity_type', $t))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
