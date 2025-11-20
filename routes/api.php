<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\ClusterController;
use App\Http\Controllers\Api\ConstructController;
use App\Http\Controllers\Api\QuestionsController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\TestTakingController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

// ============================================
// AUTHENTICATION DISABLED - All routes are public for now
// To re-enable authentication, wrap routes in: Route::middleware('auth:sanctum')->group(function () { ... });
// ============================================

// Auth routes (public)
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);

// User routes (public for now)
Route::get('users', [UserController::class, 'index']);
Route::get('users/{id}', [UserController::class, 'show']);
Route::put('users/{id}', [UserController::class, 'update']);
Route::delete('users/{id}', [UserController::class, 'destroy']);

// Options routes (public)
Route::get('options', [OptionsController::class, 'index']);
Route::post('options', [OptionsController::class, 'store']);
Route::get('options/{id}', [OptionsController::class, 'show']);
Route::put('options/{id}', [OptionsController::class, 'update']);
Route::delete('options/{id}', [OptionsController::class, 'destroy']);

// Cluster CRUD (public for now)
Route::get('clusters', [ClusterController::class, 'index']);
Route::get('clusters/{id}', [ClusterController::class, 'show']);
Route::post('clusters', [ClusterController::class, 'store']);
Route::put('clusters/{id}', [ClusterController::class, 'update']);
Route::delete('clusters/{id}', [ClusterController::class, 'destroy']);

// Construct CRUD (public for now)
Route::get('constructs', [ConstructController::class, 'index']);
Route::get('constructs/{id}', [ConstructController::class, 'show']);
Route::post('constructs', [ConstructController::class, 'store']);
Route::put('constructs/{id}', [ConstructController::class, 'update']);
Route::delete('constructs/{id}', [ConstructController::class, 'destroy']);

// Constructs by Cluster (alternative route)
Route::get('clusters/{clusterId}/constructs', [ConstructController::class, 'getByCluster']);

// Questions CRUD (public for now)
Route::get('questions', [QuestionsController::class, 'index']);
Route::get('questions/{id}', [QuestionsController::class, 'show']);
Route::post('questions', [QuestionsController::class, 'store']);
Route::put('questions/{id}', [QuestionsController::class, 'update']);
Route::delete('questions/{id}', [QuestionsController::class, 'destroy']);

// Questions by Construct
Route::get('constructs/{constructId}/questions', [QuestionsController::class, 'byConstruct']);

// Questions Bulk Upload
Route::post('questions/bulk-upload', [QuestionsController::class, 'bulkUpload']);

// Tests CRUD (public for now)
Route::get('tests', [TestController::class, 'index']);
Route::get('tests/{id}', [TestController::class, 'show']);
Route::post('tests', [TestController::class, 'store']);
Route::put('tests/{id}', [TestController::class, 'update']);
Route::delete('tests/{id}', [TestController::class, 'destroy']);

// Test Clusters Management
Route::post('tests/{id}/clusters/attach', [TestController::class, 'attachClusters']);
Route::post('tests/{id}/clusters/detach', [TestController::class, 'detachClusters']);

// Test Questions and Constructs
Route::get('tests/{id}/questions', [TestController::class, 'getQuestions']);
Route::get('tests/{id}/constructs', [TestController::class, 'getConstructs']);

// Test Question Selection
Route::put('tests/{testId}/clusters/{clusterId}/category-counts', [TestController::class, 'setClusterCategoryCounts']);
Route::post('tests/{id}/generate-questions', [TestController::class, 'generateQuestionSelection']);
Route::post('tests/{id}/regenerate-questions', [TestController::class, 'regenerateQuestionSelection']);

// Test Taking (User-facing endpoints)
Route::get('tests/{testId}/take', [TestTakingController::class, 'getTestForUser']); // Get test with questions for user
Route::post('tests/{testId}/submit', [TestTakingController::class, 'submitAnswers']); // Submit test answers
Route::get('test-results/{testResultId}', [TestTakingController::class, 'getResults']); // Get specific test result (scores only)
Route::get('test-results/{testResultId}/answers', [TestTakingController::class, 'getTestResultAnswers']); // Get questions and answers for a test result
Route::get('users/{userId}/test-results', [TestTakingController::class, 'getUserResults']); // Get all results for a user (scores only)
Route::get('tests/{testId}/results', [TestTakingController::class, 'getTestResults']); // Get all results for a test (scores only)

// Report routes
Route::get('test-results/{testResultId}/report', [ReportController::class, 'getReport']); // Get report data for a test result
Route::post('test-results/{testResultId}/report/pdf', [ReportController::class, 'storePdf']); // Store PDF file from frontend
Route::get('test-results/{testResultId}/report/pdf', [ReportController::class, 'downloadPdf']); // Download PDF report
Route::get('test-results/{testResultId}/report/view', [ReportController::class, 'viewPdf']); // View PDF report in browser
Route::put('test-results/{testResultId}/report', [ReportController::class, 'updateReportContent']); // Update report summary/recommendations

// ============================================
// TO RE-ENABLE AUTHENTICATION:
// Wrap the routes above (except register/login) in:
// Route::middleware('auth:sanctum')->group(function () { ... });
// ============================================