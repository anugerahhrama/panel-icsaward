<?php

namespace App\Http\Controllers\Judge;

use App\Actions\Judging\SaveJudgeScores;
use App\Http\Controllers\Controller;
use App\Http\Requests\Judge\ScoreRequest;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ScoreController extends Controller
{
    /**
     * Save the judge's desk evaluation scores as a draft, or submit them.
     */
    public function __invoke(ScoreRequest $request, Submission $submission, SaveJudgeScores $saveJudgeScores): RedirectResponse
    {
        Gate::authorize('score', $submission);

        $judge = $request->user()->judge;

        abort_if($judge === null, 403);

        $saveJudgeScores->handle($judge, $submission, $request->scoresData(), $request->isSubmit());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->isSubmit() ? 'Scores submitted.' : 'Draft saved.',
        ]);

        return to_route('judge.submissions.show', $submission);
    }
}
