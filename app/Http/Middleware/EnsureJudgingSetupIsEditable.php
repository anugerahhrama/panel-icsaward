<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureJudgingSetupIsEditable
{
    /**
     * Send the user back to the given route with an error toast while the judging setup is locked for them
     * (e.g. `judging.unlocked:admin.categories.index`).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $redirectRoute): Response
    {
        $access = Gate::inspect('manage-judging-setup');

        if ($access->denied()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $access->message()]);

            return to_route($redirectRoute);
        }

        return $next($request);
    }
}
