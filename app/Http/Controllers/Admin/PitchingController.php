<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Judging\SavePitchingSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PitchingScheduleRequest;
use App\Models\AwardCategory;
use App\Models\PitchingSession;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PitchingController extends Controller
{
    /**
     * List every category with its finalists and pitching session.
     */
    public function index(): Response
    {
        return Inertia::render('admin/pitching/index', [
            'categories' => AwardCategory::query()
                ->with(['pitchingSession' => fn ($query) => $query->withCount('slots')])
                ->withCount('finalists')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order', 'finalists_confirmed_at'])
                ->map(fn (AwardCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'finalists_count' => (int) $category->finalists_count,
                    'finalists_confirmed_at' => $category->finalists_confirmed_at?->toIso8601String(),
                    'session' => $category->pitchingSession === null ? null : [
                        'scheduled_at' => $category->pitchingSession->scheduled_at->toIso8601String(),
                        'location' => $category->pitchingSession->location,
                        'meeting_link' => $category->pitchingSession->meeting_link,
                        'slots_count' => (int) $category->pitchingSession->slots_count,
                    ],
                ]),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
        ]);
    }

    /**
     * Show the pitching schedule of a category whose finalists are confirmed.
     */
    public function edit(AwardCategory $category): Response|RedirectResponse
    {
        if (! $category->isFinalistsConfirmed()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Confirm the finalists of {$category->name} before scheduling their pitching."]);

            return to_route('admin.pitching.index');
        }

        $session = $category->pitchingSession;

        return Inertia::render('admin/pitching/edit', [
            'category' => $category->only(['id', 'name']),
            'session' => $session === null ? null : [
                'date' => $session->scheduled_at->setTimezone(Setting::EVENT_TIMEZONE)->format('Y-m-d'),
                'start_time' => $session->scheduled_at->setTimezone(Setting::EVENT_TIMEZONE)->format('H:i'),
                'location' => $session->location,
                'meeting_link' => $session->meeting_link,
            ],
            'finalists' => $category->finalists()
                ->with(['user:id,company_name', 'pitchingSlot'])
                ->orderBy('stage1_rank')
                ->orderBy('id')
                ->get()
                ->map(fn (Submission $submission): array => [
                    'id' => $submission->id,
                    'initiative_title' => $submission->initiative_title,
                    'company_name' => $submission->user->company_name,
                    'stage1_rank' => $submission->stage1_rank,
                    'starts_at' => $submission->pitchingSlot?->starts_at->setTimezone(Setting::EVENT_TIMEZONE)->format('H:i'),
                ]),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
        ]);
    }

    /**
     * Save a category's pitching session and its finalists' time slots.
     */
    public function update(PitchingScheduleRequest $request, AwardCategory $category, SavePitchingSchedule $saveSchedule): RedirectResponse
    {
        $saveSchedule->handle($category, $request->scheduleData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pitching schedule saved for {$category->name}."]);

        return to_route('admin.pitching.index');
    }

    /**
     * Delete a category's pitching session together with its slots.
     */
    public function destroy(AwardCategory $category): RedirectResponse
    {
        $deleted = PitchingSession::query()->where('award_category_id', $category->id)->delete();

        Inertia::flash('toast', $deleted === 0
            ? ['type' => 'error', 'message' => "{$category->name} has no pitching schedule."]
            : ['type' => 'success', 'message' => "Pitching schedule deleted for {$category->name}."]);

        return to_route('admin.pitching.index');
    }
}
