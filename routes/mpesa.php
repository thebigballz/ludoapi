<?php

use App\Http\Controllers\Mpesa\B2cResultController;
use App\Http\Controllers\Mpesa\StkCallbackController;
use Illuminate\Support\Facades\Route;

Route::middleware('mpesa.whitelist')->group(function () {
    Route::post('/stk/callback', StkCallbackController::class);
    Route::post('/b2c/result',   B2cResultController::class);
    Route::post('/b2c/queue',    B2cResultController::class);
});