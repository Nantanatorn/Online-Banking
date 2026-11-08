<?php
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SavedAccountController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');


Route::middleware('auth:api')->group(function () {
    Route::post('deposit', [TransactionController::class, 'deposit']);
    Route::post('withdraw', [TransactionController::class, 'withdraw']);
    Route::post('transfer', [TransactionController::class, 'transfer']);
    Route::get('transactions/{id}/receipt', [TransactionController::class, 'receipt']);
    Route::get('transfer/limit', [TransactionController::class, 'transferLimit']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'getUser']);
    Route::get('balance', [TransactionController::class, 'getallBalance']);
    Route::get('bal', [TransactionController::class, 'getBal']);
    Route::get('transactionhis', [TransactionController::class, 'getTransaction']);
    Route::get('accounts/{id}', [TransactionController::class, 'lookupAccount'])->middleware('throttle:30,1');
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read', [NotificationController::class, 'markAllRead']);
    Route::get('saved-accounts', [SavedAccountController::class, 'index']);
    Route::post('saved-accounts', [SavedAccountController::class, 'store']);
    Route::delete('saved-accounts/{id}', [SavedAccountController::class, 'destroy']);

});


