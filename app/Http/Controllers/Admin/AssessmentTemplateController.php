<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AssessmentTemplates\SaveAssessmentTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssessmentTemplateRequest;
use App\Models\AssessmentTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentTemplateController extends Controller
{
    /**
     * List the assessment templates.
     */
    public function index(): Response
    {
        return Inertia::render('admin/assessment-templates/index', [
            'templates' => AssessmentTemplate::query()
                ->withCount(['criteria', 'categories'])
                ->withSum('criteria', 'weight')
                ->withExists(['criteria as has_scores' => fn (Builder $query) => $query->whereHas('scores')])
                ->withCasts(['criteria_sum_weight' => 'integer'])
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
        ]);
    }

    /**
     * Show the form for creating an assessment template.
     */
    public function create(): Response
    {
        return Inertia::render('admin/assessment-templates/create');
    }

    /**
     * Create an assessment template with its scoring criteria.
     */
    public function store(AssessmentTemplateRequest $request, SaveAssessmentTemplate $saveTemplate): RedirectResponse
    {
        $template = $saveTemplate->handle(new AssessmentTemplate, $request->templateData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$template->name}\" created."]);

        return to_route('admin.assessment-templates.index');
    }

    /**
     * Show the form for editing an assessment template.
     */
    public function edit(AssessmentTemplate $template): Response
    {
        return Inertia::render('admin/assessment-templates/edit', [
            'template' => $template->only(['id', 'name', 'description']),
            'criteria' => $template->criteria()->withExists('scores as has_scores')->get(['id', 'aspect', 'criteria', 'description', 'weight']),
            'categories' => $template->categories()->orderBy('sort_order')->pluck('name'),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
        ]);
    }

    /**
     * Update an assessment template and sync its scoring criteria.
     */
    public function update(AssessmentTemplateRequest $request, AssessmentTemplate $template, SaveAssessmentTemplate $saveTemplate): RedirectResponse
    {
        $saveTemplate->handle($template, $request->templateData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$template->name}\" updated."]);

        return to_route('admin.assessment-templates.index');
    }

    /**
     * Delete an assessment template that no category uses and no judge has scored.
     */
    public function destroy(AssessmentTemplate $template): RedirectResponse
    {
        if ($template->categories()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$template->name}\" is used by a category and cannot be deleted."]);

            return to_route('admin.assessment-templates.index');
        }

        if ($template->criteria()->whereHas('scores')->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$template->name}\" has judge scores and cannot be deleted."]);

            return to_route('admin.assessment-templates.index');
        }

        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$template->name}\" deleted."]);

        return to_route('admin.assessment-templates.index');
    }
}
