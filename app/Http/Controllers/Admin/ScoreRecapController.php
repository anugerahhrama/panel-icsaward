<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Judging\CalculateStageOneScores;
use App\Actions\Judging\CalculateStageTwoScores;
use App\Actions\Judging\ConfirmFinalists;
use App\Actions\Judging\StageScoreCalculator;
use App\Enums\Announcement;
use App\Enums\Award;
use App\Enums\ScoreRecapStage;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Exports\ScoreRecapExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScoreRecapFilterRequest;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScoreRecapController extends Controller
{
    /**
     * List the submissions of the chosen stage with their stored scores and rank, filtered, sorted and paginated on the server.
     *
     * Stage 1 lists the qualified submissions and finalists, Stage 2 and Final the finalists of categories with
     * confirmed finalists.
     */
    public function index(ScoreRecapFilterRequest $request): Response
    {
        $filters = $request->filters();
        $stage = $filters['stage'];
        $calculatedAt = Submission::query()->max("{$stage->prefix()}_calculated_at");
        [$stageOneWeight, $stageTwoWeight] = CalculateStageTwoScores::weights();

        return Inertia::render('admin/score-recap/index', [
            'submissions' => Submission::query()
                ->scoreRecapForAdmin($filters)
                ->paginate($filters['per_page'])
                ->withQueryString()
                ->through(fn (Submission $submission): array => $this->recapRow($submission, $stage)),
            'filters' => $filters,
            'categories' => AwardCategory::query()
                ->withCount('finalists')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'finalists_confirmed_at', 'awards_confirmed_at', 'finalists_announced_at', 'winners_announced_at'])
                ->map(fn (AwardCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'finalists_confirmed_at' => $category->finalists_confirmed_at?->toIso8601String(),
                    'finalists_count' => $category->finalists_count,
                    'awards_confirmed_at' => $category->awards_confirmed_at?->toIso8601String(),
                    'finalists_announced' => $category->isAnnounced(Announcement::Finalists),
                    'winners_announced' => $category->isAnnounced(Announcement::Winners),
                ]),
            'candidates' => $stage !== ScoreRecapStage::DeskEvaluation || $filters['category'] === null ? null : $this->finalistCandidates($filters['category']),
            'awardCandidates' => $stage !== ScoreRecapStage::Final || $filters['category'] === null ? null : $this->awardCandidates($filters['category']),
            'awardQuota' => collect(Award::cases())->mapWithKeys(fn (Award $award): array => [$award->value => $award->quota()]),
            'maxFinalists' => ConfirmFinalists::MAX_FINALISTS,
            'canReopenFinalists' => $request->user()?->role === UserRole::Superadmin,
            'weights' => ['stage_1' => $stageOneWeight, 'stage_2' => $stageTwoWeight],
            'calculatedAt' => $calculatedAt === null ? null : Date::parse($calculatedAt)->toIso8601String(),
            'normalization' => [
                'enabled' => Setting::get(StageScoreCalculator::NORMALIZATION_ENABLED_KEY, '1') === '1',
                'min_sample' => (int) Setting::get(StageScoreCalculator::NORMALIZATION_MIN_SAMPLE_KEY, (string) StageScoreCalculator::DEFAULT_MIN_SAMPLE),
            ],
        ]);
    }

    /**
     * A Score Recap row with the rank and scores of the chosen tab. Final has no raw score or judge counts of its own.
     *
     * @return array{id: int, rank: int|null, name: string, email: string, company_name: string|null, category: string, initiative_title: string, judges_submitted: int|null, judges_assigned: int|null, raw_score: float|null, score: float|null, stage1_score: float|null, stage2_score: float|null, award: string|null, is_finalist: bool}
     */
    private function recapRow(Submission $submission, ScoreRecapStage $stage): array
    {
        [$rank, $judgesSubmitted, $judgesAssigned, $rawScore, $score] = match ($stage) {
            ScoreRecapStage::DeskEvaluation => [$submission->stage1_rank, $submission->stage1_judges_submitted, $submission->stage1_judges_assigned, $submission->stage1_raw_score, $submission->stage1_score],
            ScoreRecapStage::Pitching => [$submission->stage2_rank, $submission->stage2_judges_submitted, $submission->stage2_judges_assigned, $submission->stage2_raw_score, $submission->stage2_score],
            ScoreRecapStage::Final => [$submission->final_rank, null, null, null, $submission->final_score],
        };

        return [
            'id' => $submission->id,
            'rank' => $rank,
            'name' => $submission->user->name,
            'email' => $submission->user->email,
            'company_name' => $submission->user->company_name,
            'category' => $submission->awardCategory->name,
            'initiative_title' => $submission->initiative_title,
            'judges_submitted' => $judgesSubmitted,
            'judges_assigned' => $judgesAssigned,
            'raw_score' => $rawScore,
            'score' => $score,
            'stage1_score' => $submission->stage1_score,
            'stage2_score' => $submission->stage2_score,
            'award' => $submission->award?->value,
            'is_finalist' => $submission->status === SubmissionStatus::Finalist,
        ];
    }

    /**
     * The qualified and finalist submissions of the category the committee picks finalists from, best rank first and unranked last.
     *
     * @return array<int, array{id: int, rank: int|null, name: string, initiative_title: string, score: float|null, judges_submitted: int|null, judges_assigned: int|null, is_finalist: bool}>
     */
    private function finalistCandidates(int $categoryId): array
    {
        return Submission::query()
            ->where('award_category_id', $categoryId)
            ->whereIn('status', SubmissionStatus::rankable())
            ->with('user:id,name')
            ->orderByRaw('stage1_rank is null')
            ->orderBy('stage1_rank')
            ->orderBy('id')
            ->get()
            ->map(fn (Submission $submission): array => [
                'id' => $submission->id,
                'rank' => $submission->stage1_rank,
                'name' => $submission->user->name,
                'initiative_title' => $submission->initiative_title,
                'score' => $submission->stage1_score,
                'judges_submitted' => $submission->stage1_judges_submitted,
                'judges_assigned' => $submission->stage1_judges_assigned,
                'is_finalist' => $submission->status === SubmissionStatus::Finalist,
            ])
            ->all();
    }

    /**
     * The finalists of the category the committee gives awards to, best final rank first and unranked last, with the
     * award their rank suggests and the award they already have.
     *
     * @return array<int, array{id: int, rank: int|null, name: string, initiative_title: string, stage1_score: float|null, stage2_score: float|null, score: float|null, suggested_award: string|null, award: string|null}>
     */
    private function awardCandidates(int $categoryId): array
    {
        return Submission::query()
            ->where('award_category_id', $categoryId)
            ->where('status', SubmissionStatus::Finalist)
            ->with('user:id,name')
            ->orderByRaw('final_rank is null')
            ->orderBy('final_rank')
            ->orderBy('id')
            ->get()
            ->map(fn (Submission $submission): array => [
                'id' => $submission->id,
                'rank' => $submission->final_rank,
                'name' => $submission->user->name,
                'initiative_title' => $submission->initiative_title,
                'stage1_score' => $submission->stage1_score,
                'stage2_score' => $submission->stage2_score,
                'score' => $submission->final_score,
                'suggested_award' => Award::suggestedFor($submission->final_rank)?->value,
                'award' => $submission->award?->value,
            ])
            ->all();
    }

    /**
     * Download the Score Recap as Excel, with the same filters and sort as the table.
     */
    public function export(ScoreRecapFilterRequest $request): BinaryFileResponse
    {
        $filters = $request->filters();
        $name = match ($filters['stage']) {
            ScoreRecapStage::DeskEvaluation => 'stage-1',
            ScoreRecapStage::Pitching => 'stage-2',
            ScoreRecapStage::Final => 'final',
        };
        $timestamp = now(Setting::EVENT_TIMEZONE)->format('Ymd-Hi');

        return Excel::download(new ScoreRecapExport($filters), "score-recap-{$name}-{$timestamp}.xlsx");
    }

    /**
     * Recalculate and store the Stage 1 scores and ranks from the submitted desk evaluation scores.
     */
    public function recalculate(CalculateStageOneScores $calculate): RedirectResponse
    {
        try {
            $scored = $calculate->handle();
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Stage 1 scores recalculated for {$scored} submissions."]);

        return back();
    }

    /**
     * Recalculate and store the Stage 2 scores and ranks from the submitted pitching scores, then the final scores.
     */
    public function recalculateStageTwo(CalculateStageTwoScores $calculate): RedirectResponse
    {
        try {
            $scored = $calculate->handle();
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Stage 2 and final scores recalculated for {$scored} finalists."]);

        return back();
    }
}
