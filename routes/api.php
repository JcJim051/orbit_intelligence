<?php

use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\QgisDatasetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'active', 'abilities:meetings:upload', 'throttle:uploads'])->group(function () {
    Route::get('meetings', [MeetingController::class, 'index']);
    Route::post('meetings', [MeetingController::class, 'store']);
    Route::get('meetings/{meeting}', [MeetingController::class, 'show']);
    Route::post('meetings/{meeting}/retry', [MeetingController::class, 'retry']);
});

Route::prefix('v1/qgis')->middleware(['auth:sanctum', 'active', 'abilities:qgis:read'])->group(function () {
    Route::get('datasets', [QgisDatasetController::class, 'index'])->name('api.qgis.datasets.index');
    Route::get('datasets/{spatialDataset:slug}/form', [QgisDatasetController::class, 'show'])->name('api.qgis.datasets.form');
});
