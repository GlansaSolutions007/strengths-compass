<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\ClusterController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Options routes (public)
Route::get('options', [OptionsController::class, 'index']);
Route::post('options', [OptionsController::class, 'store']);
Route::get('options/{id}', [OptionsController::class, 'show']);
// Route::put('options/{id}', [OptionsController::class, 'update']);
// Route::delete('options/{id}', [OptionsController::class, 'destroy']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::put('users/{id}', [UserController::class, 'update']);

    // Cluster CRUD
    Route::get('/clusters', [ClusterController::class, 'index']);
    Route::get('/clusters/{id}', [ClusterController::class, 'show']);
    Route::post('/clusters', [ClusterController::class, 'store']);
    Route::put('/clusters/{id}', [ClusterController::class, 'update']);
    Route::delete('/clusters/{id}', [ClusterController::class, 'destroy']);
});