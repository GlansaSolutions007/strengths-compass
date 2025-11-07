<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\ClusterController;
use App\Http\Controllers\Api\ConstructController;
use App\Http\Controllers\Api\QuestionsController;
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
    Route::get('clusters', [ClusterController::class, 'index']);
    Route::get('clusters/{id}', [ClusterController::class, 'show']);
    Route::post('clusters', [ClusterController::class, 'store']);
    Route::put('clusters/{id}', [ClusterController::class, 'update']);
    Route::delete('clusters/{id}', [ClusterController::class, 'destroy']);

    // Construct CRUD
    Route::get('constructs', [ConstructController::class, 'index']);
    Route::get('constructs/{id}', [ConstructController::class, 'show']);
    Route::post('constructs', [ConstructController::class, 'store']);
    Route::put('constructs/{id}', [ConstructController::class, 'update']);
    Route::delete('constructs/{id}', [ConstructController::class, 'destroy']);
    
    // Constructs by Cluster (alternative route)
    Route::get('clusters/{clusterId}/constructs', [ConstructController::class, 'getByCluster']);

    // Questions CRUD
    Route::get('questions', [QuestionsController::class, 'index']);
    Route::get('questions/{id}', [QuestionsController::class, 'show']);
    Route::post('questions', [QuestionsController::class, 'store']);
    Route::put('questions/{id}', [QuestionsController::class, 'update']);
    Route::delete('questions/{id}', [QuestionsController::class, 'destroy']);

    // Questions by Construct
    Route::get('constructs/{constructId}/questions', [QuestionsController::class, 'byConstruct']);
    
    // Questions Bulk Upload
    Route::post('questions/bulk-upload', [QuestionsController::class, 'bulkUpload']);
});