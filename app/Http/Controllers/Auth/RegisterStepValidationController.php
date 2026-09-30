<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\RegistrationValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RegisterStepValidationController extends Controller
{
    use RegistrationValidationRules;

    /**
     * Validate a single sign up step before the participant moves on to the next one.
     */
    public function __invoke(Request $request, string $step): Response
    {
        $this->ensureRegistrationIsOpen();

        $rules = match ($step) {
            'account' => $this->accountRules(),
            'initiative' => $this->initiativeRules(),
            'terms' => $this->termsRules(),
            default => abort(404),
        };

        $request->validate($rules, $this->registrationMessages());

        return response()->noContent();
    }
}
