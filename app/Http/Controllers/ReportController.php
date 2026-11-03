<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** S14: basic scoped accomplishment summary. Formal exports are pilot scope (PRD F6.4). */
class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $user = $request->user();

        return Inertia::render('Reports/Index', [
            'summary' => $reports->coordinatorSummary($user),
            'events' => $reports->coordinatorEventRows($user),
        ]);
    }
}
