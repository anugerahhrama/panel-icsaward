<?php

namespace App\Http\Controllers\Judge;

use App\Enums\FilePreviewKind;
use App\Enums\JudgingStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Judge\SubmissionFilterRequest;
use App\Models\JudgeScore;
use App\Models\ScoringCriterion;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    private const string DEFAULT_SORT = 'paper_uploaded_at';

    /**
     * List the submissions the judge scores in the stage judges see, filtered, sorted and paginated on the server.
     *
     * Only the judge's own scoring status is exposed; other judges and the participant's contact details are never sent.
     */
    public function index(SubmissionFilterRequest $request): Response
    {
        $judge = $request->user()->judge;

        abort_if($judge === null, 403);

        $filters = $request->filters(self::DEFAULT_SORT);
        $stage = JudgingStage::forJudges();

        return Inertia::render('judge/submissions/index', [
            'submissions' => Submission::query()
                ->assignedToJudge($judge, $stage)
                ->withJudgeProgress($judge, $stage)
                ->filteredForJudge($judge, $stage, $filters)
                ->paginate($filters['per_page'])
                ->withQueryString()
                ->through(fn (Submission $submission): array => [
                    'id' => $submission->id,
                    'uuid' => $submission->uuid,
                    'initiative_title' => $submission->initiative_title,
                    'company' => $submission->user->company_name,
                    'category' => $submission->awardCategory->name,
                    'paper_uploaded_at' => $submission->paper_uploaded_at?->toIso8601String(),
                    'scoring_status' => $submission->judgeScoringStatus(),
                    'is_frozen' => $submission->awardCategory->isScoringFrozen($stage),
                    'scored_at' => $submission->judge_scored_at === null
                        ? null
                        : Carbon::parse($submission->judge_scored_at)->toIso8601String(),
                ]),
            'filters' => $filters,
            'categories' => $judge->categories()
                ->wherePivot('is_recused', false)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['award_categories.id', 'award_categories.name'])
                ->map(fn ($category): array => ['id' => $category->id, 'name' => $category->name]),
            'stage' => ['value' => $stage->value, 'label' => $stage->label()],
            'isScoringOpen' => JudgingStage::current() === $stage,
        ]);
    }

    /**
     * Show the scoring page: the submission, its files (download and preview links) and the rubric with the judge's own scores for the stage judges see.
     */
    public function show(Request $request, Submission $submission): Response
    {
        Gate::authorize('score', $submission);

        $judge = $request->user()->judge;

        abort_if($judge === null, 403);

        $submission->load(['user:id,company_name', 'awardCategory.assessmentTemplate.criteria']);
        $stage = JudgingStage::forJudges();
        $isFrozen = $submission->awardCategory->isScoringFrozen($stage);

        $scores = $judge->scores()
            ->where('submission_id', $submission->id)
            ->where('stage', $stage)
            ->get()
            ->keyBy('scoring_criterion_id');

        $criteria = $submission->awardCategory->assessmentTemplate === null
            ? collect()
            : $submission->awardCategory->assessmentTemplate->criteria;

        $submittedAt = $scores->pluck('submitted_at')->filter()->max();

        return Inertia::render('judge/submissions/show', [
            'submission' => [
                'uuid' => $submission->uuid,
                'initiative_title' => $submission->initiative_title,
                'initiative_description' => $submission->initiative_description,
                'company' => $submission->user->company_name,
                'category' => $submission->awardCategory->name,
                'template' => $submission->awardCategory->assessmentTemplate?->name,
                'paper_uploaded_at' => $submission->paper_uploaded_at?->toIso8601String(),
                'paper' => $submission->paper_path === null ? null : [
                    'name' => $submission->paper_original_name,
                    'url' => route('judge.submissions.files.show', [$submission, 'paper']),
                    'preview_url' => route('judge.submissions.files.preview', [$submission, 'paper']),
                    'preview_kind' => FilePreviewKind::fromFileName($submission->paper_original_name)->value,
                ],
                'statement' => $submission->statement_path === null ? null : [
                    'name' => $submission->statement_original_name,
                    'url' => route('judge.submissions.files.show', [$submission, 'statement']),
                    'preview_url' => route('judge.submissions.files.preview', [$submission, 'statement']),
                    'preview_kind' => FilePreviewKind::fromFileName($submission->statement_original_name)->value,
                ],
            ],
            'criteria' => $criteria->map(function (ScoringCriterion $criterion) use ($scores): array {
                /** @var JudgeScore|null $score */
                $score = $scores->get($criterion->id);

                return [
                    'id' => $criterion->id,
                    'aspect' => $criterion->aspect,
                    'criteria' => $criterion->criteria,
                    'description' => $criterion->description,
                    'weight' => $criterion->weight,
                    'raw_score' => $score?->raw_score,
                    'notes' => $score?->notes,
                ];
            })->values(),
            'submittedAt' => $submittedAt?->toIso8601String(),
            'stage' => ['value' => $stage->value, 'label' => $stage->label()],
            'isScoringOpen' => JudgingStage::current() === $stage && ! $isFrozen,
            'isFrozen' => $isFrozen,
        ]);
    }
}
