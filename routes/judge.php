<?php

use App\Http\Controllers\Judge\DashboardController;
use App\Http\Controllers\Judge\ScoreController;
use App\Http\Controllers\Judge\SubmissionController;
use App\Http\Controllers\SubmissionFileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:judge'])
    ->prefix('judge')
    ->name('judge.')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');

        Route::put('submissions/{submission}/scores', ScoreController::class)
            ->middleware('throttle:30,1')
            ->name('submissions.scores.update');

        Route::get('submissions/{submission}/files/{file}', SubmissionFileController::class)
            ->whereIn('file', ['paper', 'statement'])
            ->name('submissions.files.show');
    });
