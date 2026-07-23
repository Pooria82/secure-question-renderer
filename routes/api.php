<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\SecurePdfController;

Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/convert', [SecurePdfController::class, 'convert']);
    Route::get('/download/{batchId}', [SecurePdfController::class, 'download']);
});
