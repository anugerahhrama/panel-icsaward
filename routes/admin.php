<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AssessmentTemplateController;
use App\Http\Controllers\Admin\AwardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\FinalistController;
use App\Http\Controllers\Admin\JudgeController;
use App\Http\Controllers\Admin\Participants\PaperSubmissionController;
use App\Http\Controllers\Admin\Participants\RegistrationController;
use App\Http\Controllers\Admin\Participants\SubmissionFilePreviewController;
use App\Http\Controllers\Admin\Participants\VerificationController;
use App\Http\Controllers\Admin\PitchingController;
use App\Http\Controllers\Admin\ScoreRecapController;
use App\Http\Controllers\Admin\Settings\EmailSettingsController;
use App\Http\Controllers\Admin\Settings\FileSettingsController;
use App\Http\Controllers\Admin\Settings\JudgingSettingsController;
use App\Http\Controllers\Admin\Settings\LandingApiSettingsController;
use App\Http\Controllers\Admin\Settings\RegistrationSettingsController;
use App\Http\Controllers\SubmissionFileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:superadmin,admin'])
    ->prefix(config('admin.prefix'))
    ->name('admin.')
    ->group(function () {
        Route::inertia('dashboard', 'admin/dashboard')->name('dashboard');

        Route::prefix('categories')->group(function () {
            Route::resource('templates', AssessmentTemplateController::class)
                ->except(['show'])
                ->names('assessment-templates')
                ->parameters(['templates' => 'template'])
                ->middlewareFor(['create', 'store', 'update', 'destroy'], 'judging.unlocked:admin.assessment-templates.index');
        });

        Route::resource('categories', CategoryController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['categories' => 'category'])
            ->middlewareFor(['store', 'destroy'], 'judging.unlocked:admin.categories.index');
        Route::post('categories/{category}', [CategoryController::class, 'update'])
            ->middleware('judging.unlocked:admin.categories.index')
            ->name('categories.update');

        Route::resource('judges', JudgeController::class)
            ->except(['show', 'update'])
            ->parameters(['judges' => 'judge'])
            ->middlewareFor('destroy', 'judging.unlocked:admin.judges.index');
        Route::post('judges/{judge}', [JudgeController::class, 'update'])->name('judges.update');

        Route::prefix('participants')->name('participants.')->group(function () {
            Route::get('registrations', [RegistrationController::class, 'index'])->name('registrations.index');
            Route::get('registrations/export', [RegistrationController::class, 'export'])->name('registrations.export');

            Route::get('papers', [PaperSubmissionController::class, 'index'])->name('papers.index');
            Route::get('papers/export', [PaperSubmissionController::class, 'export'])->name('papers.export');

            Route::put('{submission}/verification', [VerificationController::class, 'update'])->name('verification.update');

            Route::get('{submission}/files/{file}', SubmissionFileController::class)
                ->whereIn('file', ['paper', 'statement'])
                ->name('files.show');
            Route::get('{submission}/files/{file}/preview', SubmissionFilePreviewController::class)
                ->whereIn('file', ['paper', 'statement'])
                ->name('files.preview');
        });

        Route::prefix('score-recap')->name('score-recap.')->controller(ScoreRecapController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('export', 'export')->name('export');
            Route::post('recalculate', 'recalculate')->name('recalculate');
            Route::post('stage-2/recalculate', 'recalculateStageTwo')->name('stage-2.recalculate');
        });

        Route::prefix('score-recap/categories/{category}/finalists')->name('score-recap.finalists.')->controller(FinalistController::class)->group(function () {
            Route::post('/', 'store')->name('store');
            Route::delete('/', 'destroy')->middleware('role:superadmin')->name('destroy');
        });

        Route::prefix('score-recap/categories/{category}/awards')->name('score-recap.awards.')->controller(AwardController::class)->group(function () {
            Route::post('/', 'store')->name('store');
            Route::delete('/', 'destroy')->middleware('role:superadmin')->name('destroy');
        });

        Route::prefix('pitching')->name('pitching.')->controller(PitchingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{category}/edit', 'edit')->name('edit');
            Route::middleware('judging.unlocked:admin.pitching.index')->group(function () {
                Route::put('{category}', 'update')->name('update');
                Route::delete('{category}', 'destroy')->name('destroy');
            });
        });

        Route::middleware('role:superadmin')->group(function () {
            Route::resource('accounts', AdminAccountController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['accounts' => 'account']);
            Route::patch('accounts/{account}/restore', [AdminAccountController::class, 'restore'])
                ->withTrashed()
                ->name('accounts.restore');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('registration', [RegistrationSettingsController::class, 'edit'])->name('registration.edit');
            Route::put('registration', [RegistrationSettingsController::class, 'update'])->name('registration.update');

            Route::get('files', [FileSettingsController::class, 'edit'])->name('files.edit');
            Route::post('files', [FileSettingsController::class, 'update'])->name('files.update');

            Route::get('judging', [JudgingSettingsController::class, 'edit'])->name('judging.edit');
            Route::put('judging', [JudgingSettingsController::class, 'update'])->name('judging.update');

            Route::get('email', [EmailSettingsController::class, 'edit'])->name('email.edit');
            Route::put('email', [EmailSettingsController::class, 'update'])->name('email.update');

            Route::middleware('role:superadmin')->controller(LandingApiSettingsController::class)->group(function () {
                Route::get('landing-api', 'edit')->name('landing-api.edit');
                Route::post('landing-api', 'store')->name('landing-api.store');
                Route::delete('landing-api', 'destroy')->name('landing-api.destroy');
            });
        });
    });
