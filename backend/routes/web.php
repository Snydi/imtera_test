<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/api/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/api/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
