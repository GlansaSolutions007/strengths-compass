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
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\AgeGroupController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

// ============================================
// AUTHENTICATION DISABLED - All routes are public for now
// To re-enable authentication, wrap routes in: Route::middleware('auth:sanctum')->group(function () { ... });
// ============================================

// Auth routes (public)
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::post('change-password', [AuthController::class, 'changePassword']); // Change own password (requires auth)
Route::post('forgot-password', [AuthController::class, 'forgotPassword']); // Request password reset
Route::post('reset-password', [AuthController::class, 'resetPassword']); // Reset password with temporary password
Route::post('set-age-group', [AuthController::class, 'setAgeGroup']); // Set age group (admin can select any, user only their own)
Route::get('current-age-group', [AuthController::class, 'getCurrentAgeGroup']); // Get current selected age group

// User routes (public for now)
Route::get('users', [UserController::class, 'index']);
Route::get('users/{id}', [UserController::class, 'show']);
Route::put('users/{id}', [UserController::class, 'update']);
Route::delete('users/{id}', [UserController::class, 'destroy']);
Route::post('users/{id}/change-password', [UserController::class, 'changePassword']); // Admin: Change any user's password
Route::post('users/import-school-users', [UserController::class, 'importSchoolUsers']); // Admin: Bulk import school users from Excel
Route::post('users/import-organization-users', [UserController::class, 'importOrganizationUsers']); // Admin: Bulk import organization users from Excel

// School CRUD (public for now)
Route::get('schools', [SchoolController::class, 'index']);
Route::post('schools', [SchoolController::class, 'store']);
Route::get('schools/{id}', [SchoolController::class, 'show']);
Route::put('schools/{id}', [SchoolController::class, 'update']);
Route::delete('schools/{id}', [SchoolController::class, 'destroy']);
Route::patch('schools/{id}/toggle-active', [SchoolController::class, 'toggleActive']);
Route::get('schools/{id}/users', [SchoolController::class, 'getUsers']); // Get all users for a school

// Organization CRUD (public for now)
Route::get('organizations', [OrganizationController::class, 'index']);
Route::post('organizations', [OrganizationController::class, 'store']);
Route::get('organizations/{id}', [OrganizationController::class, 'show']);
Route::put('organizations/{id}', [OrganizationController::class, 'update']);
Route::delete('organizations/{id}', [OrganizationController::class, 'destroy']);
Route::patch('organizations/{id}/toggle-active', [OrganizationController::class, 'toggleActive']);
Route::get('organizations/{id}/users', [OrganizationController::class, 'getUsers']); // Get all users for an organization

// Options routes (public)
Route::get('options', [OptionsController::class, 'index']);
Route::post('options', [OptionsController::class, 'store']);
Route::get('options/{id}', [OptionsController::class, 'show']);
Route::put('options/{id}', [OptionsController::class, 'update']);
Route::delete('options/{id}', [OptionsController::class, 'destroy']);

// Age Groups CRUD (public for now)
Route::get('age-groups', [AgeGroupController::class, 'index']);
Route::post('age-groups', [AgeGroupController::class, 'store']);
Route::get('age-groups/{id}', [AgeGroupController::class, 'show']);
Route::put('age-groups/{id}', [AgeGroupController::class, 'update']);
Route::delete('age-groups/{id}', [AgeGroupController::class, 'destroy']);
Route::patch('age-groups/{id}/toggle-active', [AgeGroupController::class, 'toggleActive']);
// Clone clusters and constructs - source can be in URL or request body
Route::post('age-groups/{id}/clone-clusters-constructs', [AgeGroupController::class, 'cloneClustersAndConstructs']);
Route::post('age-groups/clone-clusters-constructs', [AgeGroupController::class, 'cloneClustersAndConstructs']);

// Cluster CRUD (public for now)
Route::get('clusters', [ClusterController::class, 'index']);
Route::get('clusters/{id}', [ClusterController::class, 'show']);
Route::post('clusters', [ClusterController::class, 'store']);
Route::put('clusters/{id}', [ClusterController::class, 'update']);
Route::delete('clusters/{id}', [ClusterController::class, 'destroy']);
Route::patch('clusters/{id}/toggle-active', [ClusterController::class, 'toggleActive']);

// Construct CRUD (public for now)
Route::get('constructs', [ConstructController::class, 'index']);
Route::get('constructs/{id}', [ConstructController::class, 'show']);
Route::post('constructs', [ConstructController::class, 'store']);
Route::put('constructs/{id}', [ConstructController::class, 'update']);
Route::delete('constructs/{id}', [ConstructController::class, 'destroy']);
Route::patch('constructs/{id}/toggle-active', [ConstructController::class, 'toggleActive']);

// Constructs by Cluster (alternative route)
Route::get('clusters/{clusterId}/constructs', [ConstructController::class, 'getByCluster']);

// Questions CRUD (public for now)
Route::get('questions', [QuestionsController::class, 'index']);
Route::get('questions/{id}', [QuestionsController::class, 'show']);
Route::post('questions', [QuestionsController::class, 'store']);
Route::put('questions/{id}', [QuestionsController::class, 'update']);
Route::delete('questions/{id}', [QuestionsController::class, 'destroy']);
Route::patch('questions/{id}/toggle-active', [QuestionsController::class, 'toggleActive']);

// Questions by Construct
Route::get('constructs/{constructId}/questions', [QuestionsController::class, 'byConstruct']);

// Questions Bulk Upload
Route::post('questions/bulk-upload', [QuestionsController::class, 'bulkUpload']);

// Questions Construct Assignment
Route::post('questions/{id}/assign-construct', [QuestionsController::class, 'assignConstruct']);
Route::post('questions/bulk-assign-construct', [QuestionsController::class, 'bulkAssignConstruct']);

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
Route::get('test-results-comprehensive/all', [TestTakingController::class, 'getAllTestResultsComprehensive']); // Get all test results with comprehensive data for all users
Route::get('test-results-comprehensive/export', [TestTakingController::class, 'downloadTestResultsExcel']); // Download multi-sheet Excel export

// Report routes
Route::get('test-results/{testResultId}/report', [ReportController::class, 'getReport']); // Get report data for a test result
Route::post('test-results/{testResultId}/report/pdf', [ReportController::class, 'storePdf']); // Store PDF file from frontend
Route::get('test-results/{testResultId}/report/pdf', [ReportController::class, 'downloadPdf']); // Download PDF report
Route::get('test-results/{testResultId}/report/view', [ReportController::class, 'viewPdf']); // View PDF report in browser
Route::put('test-results/{testResultId}/report', [ReportController::class, 'updateReportContent']); // Update report summary/recommendations

// Countries routes (public)
Route::get('countries', [CountryController::class, 'index']);
Route::get('countries/{id}', [CountryController::class, 'show']);
Route::get('countries/{id}/states', [CountryController::class, 'getStates']);

// States routes (public)
Route::get('states', [StateController::class, 'index']);
Route::get('states/{id}', [StateController::class, 'show']);

// ============================================
// TO RE-ENABLE AUTHENTICATION:
// Wrap the routes above (except register/login) in:
// Route::middleware('auth:sanctum')->group(function () { ... });
// ============================================