<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/internal/document/{path}', function ($path) {
    // SECURITY: This route should only be accessible internally from the Docker network.
    $fullPath = storage_path('app/private/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath);
})->where('path', '.*');


use App\Http\Controllers\SecurePdfController;

Route::post('/convert', [SecurePdfController::class, 'convert']);
Route::get('/download/{batchId}', [SecurePdfController::class, 'download']);
