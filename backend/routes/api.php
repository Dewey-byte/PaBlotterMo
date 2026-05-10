<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthController::class, 'login']);

Route::get('/complaints/stats', [ComplaintController::class, 'stats']);
Route::get('/complaints', [ComplaintController::class, 'index']);
Route::post('/complaints', [ComplaintController::class, 'store']);
Route::get('/complaints/track/{trackingNumber}', [ComplaintController::class, 'track']);
Route::get('/complaints/{complaint}', [ComplaintController::class, 'show']);
Route::patch('/complaints/{complaint}', [ComplaintController::class, 'update']);

Route::get('/reports/overview', [ReportController::class, 'overview']);

Route::get('/settings', [SettingController::class, 'show']);
Route::put('/settings', [SettingController::class, 'update']);
