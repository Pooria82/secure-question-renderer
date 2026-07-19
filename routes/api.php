<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\SecurePdfController;

Route::post('/convert', [SecurePdfController::class, 'convert']);
Route::get('/download/{batchId}', [SecurePdfController::class, 'download']);
