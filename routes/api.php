<?php

use App\Http\Controllers\Api\IdentityController;
use Illuminate\Support\Facades\Route;

Route::prefix('identity')->middleware('throttle:identity-api')->group(function () {
    Route::post('touch', [IdentityController::class, 'touch'])->name('api.identity.touch');
    Route::post('resolve', [IdentityController::class, 'resolve'])->name('api.identity.resolve');
    Route::post('event', [IdentityController::class, 'event'])->name('api.identity.event');
});
