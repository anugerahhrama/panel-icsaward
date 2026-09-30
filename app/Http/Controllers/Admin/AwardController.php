<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Judging\ConfirmAwards;
use App\Actions\Judging\ReopenAwards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmAwardsRequest;
use App\Models\AwardCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AwardController extends Controller
{
    /**
     * Confirm the awards of a category and freeze its Stage 2 and final results.
     */
    public function store(ConfirmAwardsRequest $request, AwardCategory $category, ConfirmAwards $confirm): RedirectResponse
    {
        try {
            $confirmed = $confirm->handle($category, $request->awards(), $request->user());
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$confirmed} ".str('award')->plural($confirmed)." confirmed for {$category->name}."]);

        return back();
    }

    /**
     * Reopen a category's awards (superadmin only): the awards are cleared and the results are no longer frozen.
     */
    public function destroy(AwardCategory $category, ReopenAwards $reopen): RedirectResponse
    {
        try {
            $reopen->handle($category);
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Awards reopened for {$category->name}."]);

        return back();
    }
}
