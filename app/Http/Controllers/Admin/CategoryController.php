<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Categories\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * List the award categories.
     */
    public function index(): Response
    {
        return Inertia::render('admin/categories/index', [
            'categories' => AwardCategory::query()
                ->with('assessmentTemplate:id,name')
                ->withCount('submissions')
                ->withExists('judgeScores as has_scores')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'applicant_type', 'assessment_template_id', 'paper_template_path', 'paper_template_name', 'sort_order'])
                ->each->append('paper_template_url'),
            'templates' => AssessmentTemplate::query()->orderBy('name')->get(['id', 'name']),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
        ]);
    }

    /**
     * Create an award category.
     */
    public function store(CategoryRequest $request, SaveCategory $saveCategory): RedirectResponse
    {
        $category = $saveCategory->handle(new AwardCategory, $request->categoryData(), $request->paperTemplate());

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$category->name}\" created."]);

        return to_route('admin.categories.index');
    }

    /**
     * Update an award category.
     */
    public function update(CategoryRequest $request, AwardCategory $category, SaveCategory $saveCategory): RedirectResponse
    {
        $saveCategory->handle($category, $request->categoryData(), $request->paperTemplate(), $request->boolean('remove_paper_template'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$category->name}\" updated."]);

        return to_route('admin.categories.index');
    }

    /**
     * Delete an award category that has no submissions yet.
     */
    public function destroy(AwardCategory $category): RedirectResponse
    {
        if ($category->submissions()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$category->name}\" has submissions and cannot be deleted."]);

            return to_route('admin.categories.index');
        }

        $category->delete();

        if ($category->paper_template_path !== null) {
            Storage::disk(AwardCategory::PAPER_TEMPLATE_DISK)->delete($category->paper_template_path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$category->name}\" deleted."]);

        return to_route('admin.categories.index');
    }
}
