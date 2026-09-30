<?php

namespace App\Providers;

use App\Enums\JudgingStage;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
        $this->defineGates();
        $this->configureRateLimiting();
    }

    /**
     * Define the named rate limiters used by routes.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('landing-api', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
    }

    /**
     * Define the application-wide authorization gates.
     */
    protected function defineGates(): void
    {
        Gate::define('manage-judging-setup', fn (User $user): Response => $user->role === UserRole::Superadmin || JudgingStage::current() === JudgingStage::Closed
            ? Response::allow()
            : Response::deny('Judging is in progress. Only a superadmin can change this until the stage is closed.'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
