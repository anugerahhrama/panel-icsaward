<?php

namespace App\Http\Controllers\Judge;

use App\Enums\JudgingStage;
use App\Http\Controllers\Controller;
use App\Models\AwardCategory;
use App\Models\Submission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the judge's overview: active stage and scoring progress per assigned category, for the stage judges see.
     */
    public function __invoke(Request $request): Response
    {
        $judge = $request->user()->judge;

        abort_if($judge === null, 403);

        $scoringStage = JudgingStage::forJudges();

        $submissions = Submission::query()
            ->assignedToJudge($judge, $scoringStage)
            ->withJudgeProgress($judge, $scoringStage)
            ->get(['id', 'award_category_id'])
            ->groupBy('award_category_id');

        $categories = $judge->categories()
            ->wherePivot('is_recused', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['award_categories.id', 'award_categories.name'])
            ->map(function (AwardCategory $category) use ($submissions): array {
                $statuses = ($submissions->get($category->id) ?? collect())
                    ->map(fn (Submission $submission): string => $submission->judgeScoringStatus());

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'total' => $statuses->count(),
                    'submitted' => $statuses->filter(fn (string $status): bool => $status === 'submitted')->count(),
                    'draft' => $statuses->filter(fn (string $status): bool => $status === 'draft')->count(),
                ];
            });

        $stage = JudgingStage::current();

        return Inertia::render('judge/dashboard', [
            'judgeName' => $judge->name,
            'stage' => ['value' => $stage->value, 'label' => $stage->label()],
            'scoringStage' => ['value' => $scoringStage->value, 'label' => $scoringStage->label()],
            'progress' => [
                'total' => $categories->sum('total'),
                'submitted' => $categories->sum('submitted'),
                'draft' => $categories->sum('draft'),
            ],
            'categories' => $categories,
        ]);
    }
}
