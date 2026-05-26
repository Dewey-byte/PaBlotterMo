<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/password/forgot/request-otp', [AuthController::class, 'requestForgotPasswordOtp']);
Route::post('/admin/password/forgot/reset', [AuthController::class, 'resetPasswordWithOtp']);

Route::post('/complaints', [ComplaintController::class, 'store']);
Route::get('/complaints/track/{trackingNumber}', [ComplaintController::class, 'track']);

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::post('/admin/logout', [AuthController::class, 'logout']);
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('/complaints/stats', [ComplaintController::class, 'stats']);
    Route::get('/complaints/recent', [ComplaintController::class, 'recent']);
    Route::get('/complaints', [ComplaintController::class, 'index']);
    Route::get('/complaints/{complaint}/evidence/{index?}', [ComplaintController::class, 'evidence']);
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show']);
    Route::patch('/complaints/{complaint}', [ComplaintController::class, 'update']);
    Route::delete('/complaints/{complaint}', [ComplaintController::class, 'destroy']);

    Route::get('/reports/overview', [ReportController::class, 'overview']);
    Route::get('/reports/export/{format}', [ReportController::class, 'export']);

    Route::get('/settings', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);
});
