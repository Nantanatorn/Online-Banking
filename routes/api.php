<?php
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('deposit', [TransactionController::class, 'deposit']);
Route::post('withdraw', [TransactionController::class, 'withdraw']);
Route::post('tranfer', [TransactionController::class, 'transfer']);


Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'getUser']);
    Route::get('balance', [TransactionController::class, 'getallBalance']);
    Route::get('bal', [TransactionController::class, 'getBal']);
    Route::get('transactionhis', [TransactionController::class, 'getTransaction']);

});


