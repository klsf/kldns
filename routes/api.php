<?php

use App\Http\Controllers\Api\RecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('domains', [RecordController::class, 'domains']);
    Route::get('records', [RecordController::class, 'records']);
    Route::get('reviews', [RecordController::class, 'reviews']);
    Route::post('records', [RecordController::class, 'store']);
    Route::put('records/{id}', [RecordController::class, 'update']);
    Route::delete('records/{id}', [RecordController::class, 'delete']);
});
