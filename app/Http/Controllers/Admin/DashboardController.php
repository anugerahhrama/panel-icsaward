<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Overview\BuildAdminOverview;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the admin overview: phase, counts per status and category, open tasks and judging progress; the trend and
     * recent activity load after the first paint.
     */
    public function __invoke(BuildAdminOverview $overview): Response
    {
        return Inertia::render('admin/dashboard', [
            'summary' => $overview->summary(),
            'statusCounts' => $overview->statusCounts(),
            'applicantTypes' => $overview->applicantTypes(),
            'categories' => $overview->categories(),
            'attention' => $overview->attention(),
            'judgeProgress' => $overview->judgeProgress(),
            'trend' => Inertia::defer(fn (): array => $overview->trend(), 'activity'),
            'recentActivity' => Inertia::defer(fn (): array => $overview->recentActivity(), 'activity'),
        ]);
    }
}
