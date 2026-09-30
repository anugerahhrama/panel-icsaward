<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Judges\SaveJudge;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JudgeRequest;
use App\Models\AwardCategory;
use App\Models\Judge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class JudgeController extends Controller
{
    /**
     * List the judges.
     */
    public function index(): Response
    {
        $judges = Judge::query()
            ->with(['user:id,email', 'landingCategory:id,name'])
            ->withExists('scores as has_scores')
            ->withCount([
                'categories',
                'categories as recused_categories_count' => fn (Builder $query) => $query->where('category_judges.is_recused', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/judges/index', [
            'judges' => $judges->map(fn (Judge $judge): array => [
                'id' => $judge->id,
                'name' => $judge->name,
                'position' => $judge->position,
                'institution' => $judge->institution,
                'photo_url' => $judge->photo_url,
                'account_email' => $judge->user?->email,
                'show_on_landing' => $judge->show_on_landing,
                'landing_category' => $judge->landingCategory?->name,
                'sort_order' => $judge->sort_order,
                'categories_count' => $judge->categories_count,
                'recused_categories_count' => $judge->recused_categories_count,
                'has_scores' => (bool) $judge->getAttribute('has_scores'),
            ]),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
        ]);
    }

    /**
     * Show the form for creating a judge.
     */
    public function create(): Response
    {
        return Inertia::render('admin/judges/create', [
            'categories' => $this->categoryOptions(),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
        ]);
    }

    /**
     * Create a judge, optionally with a login account.
     */
    public function store(JudgeRequest $request, SaveJudge $saveJudge): RedirectResponse
    {
        $judge = $saveJudge->handle(new Judge, $request->profileData(), $request->accountData(), $request->assignmentData(), $request->photo());

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$judge->name}\" created."]);

        return to_route('admin.judges.index');
    }

    /**
     * Show the form for editing a judge.
     */
    public function edit(Request $request, Judge $judge): Response
    {
        $judge->load(['user', 'categories:id']);

        return Inertia::render('admin/judges/edit', [
            'judge' => [
                ...$judge->only(['id', 'name', 'position', 'institution', 'bio', 'show_on_landing', 'landing_category_id', 'sort_order', 'photo_url']),
                'account_email' => $judge->user?->email,
                'has_account_password' => $request->user()?->role === UserRole::Superadmin && $judge->account_password !== null,
            ],
            'assignments' => $judge->categories->map(fn (AwardCategory $category): array => [
                'award_category_id' => $category->id,
                'is_recused' => (bool) $category->getRelationValue('pivot')?->getAttribute('is_recused'),
            ]),
            'categories' => $this->categoryOptions(),
            'scoredCategoryIds' => $judge->scoredCategoryIds(),
            'canManageJudgingSetup' => Gate::allows('manage-judging-setup'),
            'accountPassword' => Inertia::optional(fn (): ?string => $this->revealAccountPassword($request, $judge)),
        ]);
    }

    /**
     * Update a judge, its login account and its category assignments.
     */
    public function update(JudgeRequest $request, Judge $judge, SaveJudge $saveJudge): RedirectResponse
    {
        $saveJudge->handle($judge, $request->profileData(), $request->accountData(), $request->assignmentData(), $request->photo(), $request->boolean('remove_photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$judge->name}\" updated."]);

        return to_route('admin.judges.index');
    }

    /**
     * Delete a judge that is not assigned to any category and has given no scores, together with its login account.
     */
    public function destroy(Judge $judge): RedirectResponse
    {
        if ($judge->categories()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$judge->name}\" is assigned to a category and cannot be deleted."]);

            return to_route('admin.judges.index');
        }

        if ($judge->scores()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "\"{$judge->name}\" has given scores and cannot be deleted."]);

            return to_route('admin.judges.index');
        }

        DB::transaction(function () use ($judge): void {
            $judge->delete();
            $judge->user?->forceDelete();
        });

        if ($judge->photo_path !== null) {
            Storage::disk(Judge::PHOTO_DISK)->delete($judge->photo_path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$judge->name}\" deleted."]);

        return to_route('admin.judges.index');
    }

    /**
     * @return Collection<int, AwardCategory>
     */
    private function categoryOptions(): Collection
    {
        return AwardCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The password the admin last set for the judge, for superadmins only.
     *
     * The stored copy is discarded once the judge has changed their own password.
     */
    private function revealAccountPassword(Request $request, Judge $judge): ?string
    {
        if ($request->user()?->role !== UserRole::Superadmin || $judge->account_password === null) {
            return null;
        }

        if ($judge->user === null || ! Hash::check($judge->account_password, $judge->user->password)) {
            $judge->forceFill(['account_password' => null])->save();

            return null;
        }

        return $judge->account_password;
    }
}
