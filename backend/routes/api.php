<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::post('/organizations/{organization}/refresh', [OrganizationController::class, 'refresh'])->whereNumber('organization');
    Route::get('/organizations/{organization}/reviews', [OrganizationController::class, 'reviews'])->whereNumber('organization');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->whereNumber('organization');
});
