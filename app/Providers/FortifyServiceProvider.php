<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\ApplicantType;
use App\Enums\RegistrationStatus;
use App\Models\AwardCategory;
use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function () {
            $status = RegistrationStatus::current();

            if (! $status->isOpen()) {
                $deadline = Setting::endOfDay('registration_deadline');

                return Inertia::render('auth/registration-closed', [
                    'status' => $status->value,
                    'opensAt' => Setting::startOfDay('registration_opens_at')?->toIso8601String(),
                    'closedAt' => $deadline?->isPast() ? $deadline->toIso8601String() : null,
                    'contactEmail' => Setting::get('contact_email'),
                ]);
            }

            $defaultPaperTemplateUrl = Setting::publicFileUrl('submission_template_path');

            return Inertia::render('auth/register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
                'categories' => AwardCategory::query()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'description', 'applicant_type', 'paper_template_path'])
                    ->map(fn (AwardCategory $category): array => [
                        ...$category->only(['id', 'name', 'description', 'applicant_type']),
                        'paper_template_url' => $category->paper_template_url ?? $defaultPaperTemplateUrl,
                    ]),
                'terms' => [
                    ApplicantType::Organization->value => Setting::get('terms_organization', ''),
                    ApplicantType::Individual->value => Setting::get('terms_individual', ''),
                ],
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
