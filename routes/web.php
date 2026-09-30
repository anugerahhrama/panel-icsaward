<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\RegisterStepValidationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SharedSubmissionFileController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionFileController;
use App\Http\Controllers\SubmissionPaperController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::post('register/validate/{step}', RegisterStepValidationController::class)
    ->middleware(['guest', 'throttle:30,1'])
    ->whereIn('step', ['account', 'initiative', 'terms'])
    ->name('register.validate');

Route::middleware('guest')->prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('redirect', [GoogleLoginController::class, 'redirect'])->name('redirect');
    Route::get('callback', [GoogleLoginController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('callback');
});

Route::get('submissions/{submission}/files/{file}/shared', SharedSubmissionFileController::class)
    ->middleware(['signed:relative', 'throttle:60,1'])
    ->whereIn('file', ['paper', 'statement'])
    ->name('submissions.files.shared');

Route::middleware('auth')->group(function () {
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::post('submissions/{submission}/paper', SubmissionPaperController::class)
        ->middleware('throttle:10,1')
        ->name('submissions.paper.store');
    Route::get('submissions/{submission}/files/{file}', SubmissionFileController::class)
        ->whereIn('file', ['paper', 'statement'])
        ->name('submissions.files.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
