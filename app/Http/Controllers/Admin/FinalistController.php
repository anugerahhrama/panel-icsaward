<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Judging\ConfirmFinalists;
use App\Actions\Judging\ReopenFinalists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmFinalistsRequest;
use App\Models\AwardCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FinalistController extends Controller
{
    /**
     * Confirm the selected finalists of a category and freeze its Stage 1 results.
     */
    public function store(ConfirmFinalistsRequest $request, AwardCategory $category, ConfirmFinalists $confirm): RedirectResponse
    {
        try {
            $confirmed = $confirm->handle($category, $request->submissionIds(), $request->user());
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$confirmed} ".str('finalist')->plural($confirmed)." confirmed for {$category->name}."]);

        return back();
    }

    /**
     * Reopen a category's finalists (superadmin only): they go back to qualified and the results are no longer frozen.
     */
    public function destroy(AwardCategory $category, ReopenFinalists $reopen): RedirectResponse
    {
        try {
            $reopen->handle($category);
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Finalists reopened for {$category->name}."]);

        return back();
    }
}
