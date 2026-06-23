<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Game\GameTableController;
use App\Http\Controllers\Wallet\DepositController;
use App\Http\Controllers\Wallet\WalletController;
use App\Http\Controllers\Wallet\WithdrawalController;
use App\Http\Controllers\Leaderboard\LeaderboardController;
use App\Http\Controllers\Auth\FirebaseTokenController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminGameController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\Admin\AdminFraudFlagController;
use App\Http\Controllers\Admin\AdminUserController;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('/auth/register', RegisterController::class);
    Route::post('/auth/login',    LoginController::class);

    // Authenticated
    Route::middleware(['auth:sanctum', 'banned'])->group(function () {

        Route::post('/auth/logout', LogoutController::class);
		Route::get('/leaderboard', [LeaderboardController::class, 'index']);
		Route::post('/auth/firebase-token', FirebaseTokenController::class);

        // Wallet
        Route::prefix('wallet')->group(function () {
            Route::get('/',             [WalletController::class, 'balance']);
            Route::get('/transactions', [WalletController::class, 'transactions']);
            Route::post('/deposit',     DepositController::class);
            Route::post('/withdraw',    WithdrawalController::class);
        });

        // Games — users
        Route::prefix('games')->group(function () {
            Route::get('/',              [GameTableController::class, 'index']);
            Route::post('/join',         [GameTableController::class, 'join']);
            Route::post('/result',       [GameTableController::class, 'result']);
			Route::post('/{game}/leave', [GameTableController::class, 'leave']);
        });

        // Admin only
Route::middleware('admin')->prefix('admin')->group(function () {

    Route::prefix('games')->group(function () {
        Route::get('/',               [AdminGameController::class, 'index']);
        Route::post('/create',        [GameTableController::class, 'create']);
        Route::post('/{game}/cancel', [GameTableController::class, 'cancel']);
    });

    Route::prefix('withdrawals')->group(function () {
        Route::get('/',                       [AdminWithdrawalController::class, 'index']);
        Route::post('/{withdrawal}/approve',  [AdminWithdrawalController::class, 'approve']);
        Route::post('/{withdrawal}/reject',   [AdminWithdrawalController::class, 'reject']);
    });

    Route::prefix('fraud-flags')->group(function () {
        Route::get('/',                 [AdminFraudFlagController::class, 'index']);
        Route::post('/{flag}/resolve',  [AdminFraudFlagController::class, 'resolve']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/',                   [AdminUserController::class, 'index']);
        Route::post('/{user}/toggle-ban', [AdminUserController::class, 'toggleBan']);
    });
});

    });

});