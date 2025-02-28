<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('api/register', [AuthController::class, 'register']);
Route::post('api/login', [AuthController::class, 'login']);

Route::middleware('auth:jwt')->group(function () {
    Route::post('api/logout', [AuthController::class, 'logout']);
    Route::get('api/user', [AuthController::class, 'getUser']);
});

