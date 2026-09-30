<?php

use App\Http\Controllers\Api\V1\Landing\CategoryController;
use App\Http\Controllers\Api\V1\Landing\JudgeController;
use App\Http\Controllers\Api\V1\Landing\StatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:landing-api', 'landing.token'])
    ->prefix('v1/landing')
    ->name('api.v1.landing.')
    ->group(function () {
        Route::get('categories', CategoryController::class)->name('categories');
        Route::get('judges', JudgeController::class)->name('judges');
        Route::get('stats', StatsController::class)->name('stats');
    });
