<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Test;
use App\Models\Cluster;
use App\Models\QuestionsModel as Question;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TestQuestionsTemplateExport;
use App\Imports\TestQuestionsImport;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Test::with(['clusters', 'ageGroup']);

        // Filter by age_group_id if provided in request
        if ($request->has('age_group_id')) {
            $query->where('age_group_id', $request->age_group_id);
        }

        // Filter by is_active if provided
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by source if provided
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $tests = $query->get();

        return response()->json([
            'status' => true,
            'data' => $tests,
            'message' => 'Tests fetched successfully'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(Request $request)
    // {
    //     // Debug: Log the request method and data
    //     \Log::info('Test Store Method Called', [
    //         'method' => $request->method(),
    //         'url' => $request->fullUrl(),
    //         'data' => $request->all()
    //     ]);

    //     $validator = Validator::make($request->all(), [
    //         'title' => 'required|string|max:255',
    //         'description' => 'nullable|string',
    //         'age_group_id' => 'nullable|exists:age_groups,id',
    //         'is_active' => 'sometimes|boolean',
    //         'cluster_ids' => 'sometimes|array',
    //         'cluster_ids.*' => 'exists:clusters,id',
    //         'clusters' => 'sometimes|array',
    //         'clusters.*.cluster_id' => 'required|exists:clusters,id',
    //         'clusters.*.p_count' => 'nullable|integer|min:0',
    //         'clusters.*.r_count' => 'nullable|integer|min:0',
    //         'clusters.*.sdb_count' => 'nullable|integer|min:0',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'errors' => $validator->errors(),
    //             'message' => 'Validation failed'
    //         ], 422);
    //     }

    //     try {
    //         $test = Test::create($request->only(['title', 'description', 'age_group_id', 'is_active']));

    //         // Handle cluster_ids (simple array format - backward compatibility)
    //         if ($request->has('cluster_ids') && is_array($request->cluster_ids)) {
    //             foreach ($request->cluster_ids as $clusterId) {
    //                 $test->clusters()->attach($clusterId, [
    //                     'p_count' => null,
    //                     'r_count' => null,
    //                     'sdb_count' => null,
    //                 ]);
    //             }
    //         }

    //         // Handle clusters (nested format with category counts)
    //         if ($request->has('clusters')) {
    //             foreach ($request->clusters as $clusterData) {
    //                 $test->clusters()->attach($clusterData['cluster_id'], [
    //                     'p_count' => $clusterData['p_count'] ?? null,
    //                     'r_count' => $clusterData['r_count'] ?? null,
    //                     'sdb_count' => $clusterData['sdb_count'] ?? null,
    //                 ]);
    //             }
    //         }

    //         $test->load('clusters');

    //         // Auto-generate questions if clusters are attached
    //         if ($test->clusters->count() > 0) {
    //             $this->generateQuestionSelectionInternal($test);
    //         }

    //         // Reload test with questions to include in response
    //         $test->load('selectedQuestions');

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Test created successfully',
    //             'data' => $test,
    //             'selected_questions_count' => $test->selectedQuestions->count()
    //         ], 201);
    //     } catch (\Exception $e) {
    //         \Log::error('Test Creation Error', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Error creating test: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function store(Request $request)
    {
        // Debug: Log the request method and data
        \Log::info('Test Store Method Called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'data' => $request->all()
        ]);

        // Normalize question_ids so frontend can send array [738,739,740] or string "738,739,740" (e.g. with FormData + file)
        $request->merge(['question_ids' => $this->normalizeQuestionIdsInput($request->input('question_ids'))]);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'age_group_id' => 'nullable|exists:age_groups,id',
            'is_active' => 'sometimes|boolean',
            'source' => 'sometimes|in:SC Pro,CERC',
            'sc_pro_test_id' => [
                'nullable',
                'exists:tests,id',
                function ($attribute, $value, $fail) use ($request) {
                    // If source is CERC, sc_pro_test_id should be provided
                    if ($request->input('source') === 'CERC' && !$value) {
                        $fail('sc_pro_test_id is required for CERC tests to map to the corresponding SC Pro test.');
                    }
                    // If sc_pro_test_id is provided, verify it's an SC Pro test
                    if ($value) {
                        $scProTest = Test::find($value);
                        if (!$scProTest || ($scProTest->source ?? 'SC Pro') !== 'SC Pro') {
                            $fail('sc_pro_test_id must reference an SC Pro test.');
                        }
                    }
                },
            ],
            'cluster_ids' => 'sometimes|array',
            'cluster_ids.*' => 'exists:clusters,id',
            'construct_ids' => 'sometimes|array',
            'construct_ids.*' => 'exists:constructs,id',
            'question_ids' => 'sometimes|array',
            'question_ids.*' => 'exists:questions,id',
            'clusters' => 'sometimes|array',
            'clusters.*.cluster_id' => 'required|exists:clusters,id',
            'clusters.*.p_count' => 'nullable|integer|min:0',
            'clusters.*.r_count' => 'nullable|integer|min:0',
            'clusters.*.sdb_count' => 'nullable|integer|min:0',
            'questions_file' => 'sometimes|mimes:xlsx,xls,csv|max:10240', // Excel file for questions
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            $test = Test::create($request->only(['title', 'description', 'age_group_id', 'is_active', 'source', 'sc_pro_test_id']));

            // Handle cluster_ids (simple array format - backward compatibility)
            if ($request->has('cluster_ids') && is_array($request->cluster_ids)) {
                foreach ($request->cluster_ids as $clusterId) {
                    $test->clusters()->attach($clusterId, [
                        'p_count' => null,
                        'r_count' => null,
                        'sdb_count' => null,
                    ]);
                }
            }

            // Handle clusters (nested format with category counts)
            if ($request->has('clusters')) {
                foreach ($request->clusters as $clusterData) {
                    $test->clusters()->attach($clusterData['cluster_id'], [
                        'p_count' => $clusterData['p_count'] ?? null,
                        'r_count' => $clusterData['r_count'] ?? null,
                        'sdb_count' => $clusterData['sdb_count'] ?? null,
                    ]);
                }
            }

            $test->load('clusters');

            // Store import stats for response
            $importStats = null;
            $importErrors = null;

            // CERC / questions: support both selected question_ids AND Excel file (selected first, then Excel)
            $hasFile = $request->hasFile('questions_file');
            $hasQuestionIds = $request->has('question_ids') && is_array($request->question_ids) && count($request->question_ids) > 0;

            if ($hasQuestionIds) {
                // Attach selected questions first (order_no 1, 2, 3, ...)
                $this->attachSelectedQuestions($test, $request->question_ids);
            }

            if ($hasFile) {
                try {
                    $file = $request->file('questions_file');
                    $ageGroupId = $test->age_group_id;

                    \Log::info('Starting Excel import for test', [
                        'test_id' => $test->id,
                        'age_group_id' => $ageGroupId,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'has_selected_questions' => $hasQuestionIds,
                    ]);

                    // Create import instance with test source and SC Pro test id (for CERC duplicate check)
                    $import = new TestQuestionsImport($test->id, $ageGroupId, $test->source ?? 'SC Pro', $test->sc_pro_test_id ?? null);

                    Excel::import($import, $file);

                    $stats = $import->getStats();
                    $createdQuestions = $stats['created_questions'] ?? [];
                    $errors = $stats['errors'] ?? [];

                    \Log::info('Excel import completed', [
                        'test_id' => $test->id,
                        'success_count' => $stats['success'] ?? 0,
                        'failure_count' => $stats['failures'] ?? 0,
                        'questions_created' => count($createdQuestions),
                        'errors_count' => count($errors),
                    ]);

                    $importStats = $stats;
                    $importErrors = $errors;

                    $clusterIds = [];
                    foreach ($createdQuestions as $item) {
                        $clusterId = $item['cluster_id'];
                        if ($clusterId && !in_array($clusterId, $clusterIds)) {
                            $clusterIds[] = $clusterId;
                        }
                    }

                    if (!empty($clusterIds)) {
                        foreach ($clusterIds as $clusterId) {
                            if (!$test->clusters()->where('clusters.id', $clusterId)->exists()) {
                                $test->clusters()->attach($clusterId, [
                                    'p_count' => null,
                                    'r_count' => null,
                                    'sdb_count' => null,
                                ]);
                            }
                        }
                    }

                    // Attach Excel questions with order_no after any selected questions
                    if (!empty($createdQuestions)) {
                        $maxOrderNo = (int) DB::table('test_question')
                            ->where('test_id', $test->id)
                            ->max('order_no');
                        $orderNo = $maxOrderNo + 1;

                        foreach ($createdQuestions as $item) {
                            $questionId = $item['question_id'];
                            $clusterId = $item['cluster_id'];
                            DB::table('test_question')->updateOrInsert(
                                [
                                    'test_id' => $test->id,
                                    'question_id' => $questionId,
                                ],
                                [
                                    'cluster_id' => $clusterId,
                                    'order_no' => $orderNo,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                            $orderNo++;
                        }
                    }

                    \Log::info('Test questions import completed', [
                        'test_id' => $test->id,
                        'success_count' => $stats['success'] ?? 0,
                        'failure_count' => $stats['failures'] ?? 0,
                        'questions_attached' => count($createdQuestions),
                        'clusters_attached' => count($clusterIds),
                        'errors' => $errors,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Test questions import failed during test creation', [
                        'test_id' => $test->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } elseif (!$hasQuestionIds) {
                // Auto-generate questions if clusters are attached and no question_ids provided
                if ($test->clusters->count() > 0) {
                    $this->generateQuestionSelectionInternal($test);
                }
            }

            // Reload test with questions and clusters to include in response
            $test->load(['selectedQuestions', 'clusters']);

            $response = [
                'status' => true,
                'message' => 'Test created successfully',
                'data' => $test,
                'selected_questions_count' => $test->selectedQuestions->count()
            ];

            // If Excel file was uploaded, include import statistics
            if ($importStats !== null) {
                $response['import_stats'] = [
                    'success_count' => $importStats['success'] ?? 0,
                    'failure_count' => $importStats['failures'] ?? 0,
                    'questions_created' => count($importStats['created_questions'] ?? []),
                ];
                
                if (!empty($importErrors)) {
                    $response['import_errors'] = $importErrors;
                    $response['message'] = 'Test created with some import errors. Check import_errors for details.';
                } elseif (($importStats['success'] ?? 0) > 0) {
                    $response['message'] = 'Test created successfully with ' . ($importStats['success'] ?? 0) . ' questions imported.';
                }
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            \Log::error('Test Creation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error creating test: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $test = Test::with(['clusters', 'clusters.constructs', 'ageGroup'])->find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        // Get selected questions (if any) or available questions
        $selectedQuestions = $test->selectedQuestions()->get();
        
        $testData = $test->toArray();
        $testData['selected_questions'] = $selectedQuestions;
        $testData['selected_questions_count'] = $selectedQuestions->count();

        return response()->json([
            'status' => true,
            'data' => $testData,
            'message' => 'Test fetched successfully'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'age_group_id' => 'nullable|exists:age_groups,id',
            'is_active' => 'sometimes|boolean',
            'source' => 'sometimes|in:SC Pro,CERC',
            'sc_pro_test_id' => [
                'nullable',
                'exists:tests,id',
                function ($attribute, $value, $fail) use ($request, $id) {
                    // If source is CERC, sc_pro_test_id should be provided
                    if ($request->input('source') === 'CERC' && !$value) {
                        $fail('sc_pro_test_id is required for CERC tests to map to the corresponding SC Pro test.');
                    }
                    // If sc_pro_test_id is provided, verify it's an SC Pro test
                    if ($value) {
                        $scProTest = Test::find($value);
                        if (!$scProTest || ($scProTest->source ?? 'SC Pro') !== 'SC Pro') {
                            $fail('sc_pro_test_id must reference an SC Pro test.');
                        }
                        // Prevent circular reference
                        if ($value == $id) {
                            $fail('A test cannot reference itself as sc_pro_test_id.');
                        }
                    }
                },
            ],
            'question_ids' => 'sometimes|array',
            'question_ids.*' => 'exists:questions,id',
            'clusters' => 'sometimes|array',
            'clusters.*.cluster_id' => 'required|exists:clusters,id',
            'clusters.*.p_count' => 'nullable|integer|min:0',
            'clusters.*.r_count' => 'nullable|integer|min:0',
            'clusters.*.sdb_count' => 'nullable|integer|min:0',
            'questions_file' => 'sometimes|mimes:xlsx,xls,csv|max:10240', // Excel file for questions
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $test->update($request->only(['title', 'description', 'age_group_id', 'is_active', 'source', 'sc_pro_test_id']));

        // Sync clusters with category counts if provided AND not empty
        // Only sync if clusters array is present, not empty, and is actually an array
        if ($request->has('clusters') && is_array($request->clusters) && !empty($request->clusters)) {
            $syncData = [];
            foreach ($request->clusters as $clusterData) {
                $syncData[$clusterData['cluster_id']] = [
                    'p_count' => $clusterData['p_count'] ?? null,
                    'r_count' => $clusterData['r_count'] ?? null,
                    'sdb_count' => $clusterData['sdb_count'] ?? null,
                ];
            }
            $test->clusters()->sync($syncData);
        }

        $test->load('clusters');

        // Handle Excel file upload for questions (priority)
        if ($request->hasFile('questions_file')) {
            try {
                $file = $request->file('questions_file');
                $ageGroupId = $test->age_group_id;

                // Create import instance with test source and SC Pro test id (for CERC duplicate check)
                $import = new TestQuestionsImport($test->id, $ageGroupId, $test->source ?? 'SC Pro', $test->sc_pro_test_id ?? null);

                // Import the file
                Excel::import($import, $file);

                // Get import statistics
                $stats = $import->getStats();
                $createdQuestions = $stats['created_questions'] ?? [];

                // Clear existing questions and attach new ones
                DB::table('test_question')->where('test_id', $test->id)->delete();

                    // Attach created questions to the test
                    if (!empty($createdQuestions)) {
                        $testQuestions = [];
                        $orderNo = 1;

                        foreach ($createdQuestions as $item) {
                            $questionId = $item['question_id'];
                            $clusterId = $item['cluster_id'];

                            $testQuestions[] = [
                                'test_id' => $test->id,
                                'question_id' => $questionId,
                                'cluster_id' => $clusterId,
                                'order_no' => $orderNo++,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (!empty($testQuestions)) {
                            DB::table('test_question')->insert($testQuestions);
                        }
                    }
            } catch (\Exception $e) {
                \Log::error('Test questions import failed during test update', [
                    'test_id' => $test->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue with test update even if import fails
            }
        }
        // Handle question_ids if provided (manual selection from frontend)
        elseif ($request->has('question_ids') && is_array($request->question_ids) && count($request->question_ids) > 0) {
            $this->attachSelectedQuestions($test, $request->question_ids);
        }

        $test->load('selectedQuestions');

        return response()->json([
            'status' => true,
            'message' => 'Test updated successfully',
            'data' => $test,
            'selected_questions_count' => $test->selectedQuestions->count()
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        // Detach all clusters and remove selected questions before deleting
        $test->clusters()->detach();
        $test->selectedQuestions()->detach();

        $test->delete();

        return response()->json([
            'status' => true,
            'message' => 'Test deleted successfully'
        ], 200);
    }

    /**
     * Attach clusters to a test
     */
    public function attachClusters(Request $request, string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'clusters' => 'required|array',
            'clusters.*.cluster_id' => 'required|exists:clusters,id',
            'clusters.*.p_count' => 'nullable|integer|min:0',
            'clusters.*.r_count' => 'nullable|integer|min:0',
            'clusters.*.sdb_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        // Attach clusters with category counts
        foreach ($request->clusters as $clusterData) {
            if (!$test->clusters()->where('clusters.id', $clusterData['cluster_id'])->exists()) {
                $test->clusters()->attach($clusterData['cluster_id'], [
                    'p_count' => $clusterData['p_count'] ?? null,
                    'r_count' => $clusterData['r_count'] ?? null,
                    'sdb_count' => $clusterData['sdb_count'] ?? null,
                ]);
            }
        }

        $test->load('clusters');

        return response()->json([
            'status' => true,
            'message' => 'Clusters attached successfully',
            'data' => $test
        ], 200);
    }

    /**
     * Detach clusters from a test
     */
    public function detachClusters(Request $request, string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'cluster_ids' => 'required|array',
            'cluster_ids.*' => 'exists:clusters,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $test->clusters()->detach($request->cluster_ids);

        $test->load('clusters');

        return response()->json([
            'status' => true,
            'message' => 'Clusters detached successfully',
            'data' => $test
        ], 200);
    }

    /**
     * Get CERC tests linked to an SC Pro test
     */
    public function getCercTestsForScPro(string $id)
    {
        $scProTest = Test::find($id);

        if (!$scProTest) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        if (($scProTest->source ?? 'SC Pro') !== 'SC Pro') {
            return response()->json([
                'status' => false,
                'message' => 'This endpoint is only for SC Pro tests'
            ], 422);
        }

        // Get all CERC tests linked to this SC Pro test
        $cercTests = Test::where('sc_pro_test_id', $scProTest->id)
            ->where('source', 'CERC')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'sc_pro_test' => [
                    'id' => $scProTest->id,
                    'title' => $scProTest->title,
                    'source' => $scProTest->source ?? 'SC Pro',
                ],
                'cerc_tests' => $cercTests->map(function ($test) {
                    return [
                        'id' => $test->id,
                        'title' => $test->title,
                        'description' => $test->description,
                        'source' => $test->source,
                        'age_group_id' => $test->age_group_id,
                        'is_active' => $test->is_active,
                    ];
                })
            ],
            'message' => 'CERC tests fetched successfully',
            'count' => $cercTests->count()
        ], 200);
    }

    /**
     * Get selected questions for a test
     */
    public function getQuestions(string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        $questions = $test->selectedQuestions()->get();

        return response()->json([
            'status' => true,
            'data' => $questions,
            'message' => 'Selected questions fetched successfully',
            'count' => $questions->count()
        ], 200);
    }

    /**
     * Get all constructs for a test
     */
    public function getConstructs(string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        $constructs = $test->constructs;

        return response()->json([
            'status' => true,
            'data' => $constructs,
            'message' => 'Constructs fetched successfully'
        ], 200);
    }

    /**
     * Set category counts for a specific cluster in a test
     */
    public function setClusterCategoryCounts(Request $request, string $testId, string $clusterId)
    {
        $test = Test::find($testId);
        $cluster = Cluster::find($clusterId);

        if (!$test || !$cluster) {
            return response()->json([
                'status' => false,
                'message' => 'Test or Cluster not found'
            ], 404);
        }

        // Check if cluster is attached to test
        if (!$test->clusters()->where('clusters.id', $clusterId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Cluster is not attached to this test'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'p_count' => 'nullable|integer|min:0',
            'r_count' => 'nullable|integer|min:0',
            'sdb_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        // Update pivot table
        $test->clusters()->updateExistingPivot($clusterId, [
            'p_count' => $request->input('p_count'),
            'r_count' => $request->input('r_count'),
            'sdb_count' => $request->input('sdb_count'),
        ]);

        $test->load('clusters');

        return response()->json([
            'status' => true,
            'message' => 'Category counts updated successfully',
            'data' => $test
        ], 200);
    }

    /**
     * Internal method to generate question selection (can be called from store or generateQuestionSelection)
     */
    private function generateQuestionSelectionInternal(Test $test)
    {
        $test->load('clusters');
        
        $selectedQuestions = [];
        $errors = [];

        // Process each cluster - collect questions WITHOUT order_no first
        foreach ($test->clusters as $cluster) {
            $pivot = $cluster->pivot;
            $pCount = $pivot->p_count ?? null;
            $rCount = $pivot->r_count ?? null;
            $sdbCount = $pivot->sdb_count ?? null;

            // Get all active questions in this cluster for this test (test_cluster_construct or legacy cluster_id)
            $constructIdsInCluster = DB::table('test_cluster_construct')
                ->where('test_id', $test->id)
                ->where('cluster_id', $cluster->id)
                ->pluck('construct_id')
                ->all();
            if (!empty($constructIdsInCluster)) {
                $availableQuestions = Question::whereIn('construct_id', $constructIdsInCluster)->where('is_active', true);
            } else {
                $availableQuestions = Question::whereHas('construct', function ($query) use ($cluster) {
                    $query->where('cluster_id', $cluster->id);
                })->where('is_active', true);
            }
            
            // If test source is CERC, include questions from both sources
            // If test source is SC Pro, only include SC Pro questions
            if ($test->source === 'CERC') {
                $availableQuestions = $availableQuestions->whereIn('source', ['SC Pro', 'CERC']);
            } else {
                // Default to SC Pro if source is not set or is SC Pro
                $availableQuestions = $availableQuestions->where('source', 'SC Pro');
            }
            
            $availableQuestions = $availableQuestions->get();

            // If no category counts are set, include ALL questions (backward compatibility)
            if ($pCount === null && $rCount === null && $sdbCount === null) {
                foreach ($availableQuestions as $question) {
                    $selectedQuestions[] = [
                        'test_id' => $test->id,
                        'question_id' => $question->id,
                        'cluster_id' => $cluster->id,
                        // order_no will be assigned after shuffling
                    ];
                }
                continue;
            }

            // Category counts are set - use auto-pick logic
            $pCount = $pCount ?? 0;
            $rCount = $rCount ?? 0;
            $sdbCount = $sdbCount ?? 0;

            // Skip if all counts are 0
            if ($pCount == 0 && $rCount == 0 && $sdbCount == 0) {
                continue;
            }

            // Group by category
            $questionsByCategory = [
                'P' => $availableQuestions->where('category', 'P')->shuffle(),
                'R' => $availableQuestions->where('category', 'R')->shuffle(),
                'SDB' => $availableQuestions->where('category', 'SDB')->shuffle(),
            ];

            // Select questions for each category
            $clusterErrors = [];

            // Select P questions
            if ($pCount > 0) {
                $pQuestions = $questionsByCategory['P']->take($pCount);
                if ($pQuestions->count() < $pCount) {
                    $clusterErrors[] = "Cluster '{$cluster->name}': Only {$pQuestions->count()} P questions available, requested {$pCount}";
                }
                foreach ($pQuestions as $question) {
                    $selectedQuestions[] = [
                        'test_id' => $test->id,
                        'question_id' => $question->id,
                        'cluster_id' => $cluster->id,
                        // order_no will be assigned after shuffling
                    ];
                }
            }

            // Select R questions
            if ($rCount > 0) {
                $rQuestions = $questionsByCategory['R']->take($rCount);
                if ($rQuestions->count() < $rCount) {
                    $clusterErrors[] = "Cluster '{$cluster->name}': Only {$rQuestions->count()} R questions available, requested {$rCount}";
                }
                foreach ($rQuestions as $question) {
                    $selectedQuestions[] = [
                        'test_id' => $test->id,
                        'question_id' => $question->id,
                        'cluster_id' => $cluster->id,
                        // order_no will be assigned after shuffling
                    ];
                }
            }

            // Select SDB questions
            if ($sdbCount > 0) {
                $sdbQuestions = $questionsByCategory['SDB']->take($sdbCount);
                if ($sdbQuestions->count() < $sdbCount) {
                    $clusterErrors[] = "Cluster '{$cluster->name}': Only {$sdbQuestions->count()} SDB questions available, requested {$sdbCount}";
                }
                foreach ($sdbQuestions as $question) {
                    $selectedQuestions[] = [
                        'test_id' => $test->id,
                        'question_id' => $question->id,
                        'cluster_id' => $cluster->id,
                        // order_no will be assigned after shuffling
                    ];
                }
            }

            if (!empty($clusterErrors)) {
                $errors = array_merge($errors, $clusterErrors);
            }
        }

        // Remove duplicates (same question shouldn't be added twice)
        $uniqueQuestions = [];
        $seenQuestionIds = [];
        foreach ($selectedQuestions as $sq) {
            if (!in_array($sq['question_id'], $seenQuestionIds)) {
                $uniqueQuestions[] = $sq;
                $seenQuestionIds[] = $sq['question_id'];
            }
        }

        // Shuffle all questions to mix them randomly across clusters
        shuffle($uniqueQuestions);

        // Now assign sequential order_no after shuffling
        $orderNo = 1;
        foreach ($uniqueQuestions as &$question) {
            $question['order_no'] = $orderNo++;
        }
        unset($question); // Break reference

        // Clear existing selections and insert new ones
        DB::table('test_question')->where('test_id', $test->id)->delete();

        if (!empty($uniqueQuestions)) {
            DB::table('test_question')->insert($uniqueQuestions);
        }

        return [
            'selected_count' => count($uniqueQuestions),
            'total_requested' => count($selectedQuestions),
            'errors' => $errors
        ];
    }

    /**
     * Generate question selection based on category counts
     * If no category counts are set, includes ALL questions (backward compatibility)
     */
    public function generateQuestionSelection(string $id)
    {
        $test = Test::with('clusters')->find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        $result = $this->generateQuestionSelectionInternal($test);

        $response = [
            'status' => true,
            'message' => 'Question selection generated successfully',
            'data' => [
                'test_id' => $test->id,
                'selected_count' => $result['selected_count'],
                'total_requested' => $result['total_requested'],
            ]
        ];

        if (!empty($result['errors'])) {
            $response['warnings'] = $result['errors'];
            $response['message'] = 'Question selection generated with warnings';
        }

        return response()->json($response, 200);
    }

    /**
     * Regenerate question selection (same as generate but clears first)
     */
    public function regenerateQuestionSelection(string $id)
    {
        return $this->generateQuestionSelection($id);
    }

    /**
     * Normalize question_ids from request: accept array [738,739,740] or string "738,739,740" / "[738,739,740]"
     * so that FormData + file upload still sends selected questions correctly.
     *
     * @param mixed $input
     * @return array<int>
     */
    private function normalizeQuestionIdsInput($input): array
    {
        if ($input === null || $input === '') {
            return [];
        }
        if (is_array($input)) {
            $ids = [];
            foreach ($input as $id) {
                $id = is_numeric($id) ? (int) $id : (int) trim((string) $id);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            return array_values(array_unique($ids));
        }
        $raw = trim((string) $input);
        if ($raw === '') {
            return [];
        }
        // JSON array string e.g. "[738,739,740]"
        if (strpos($raw, '[') === 0) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeQuestionIdsInput($decoded);
            }
        }
        // Comma-separated e.g. "738,739,740"
        $ids = [];
        foreach (explode(',', $raw) as $id) {
            $id = (int) trim($id);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function attachSelectedQuestions(Test $test, array $questionIds)
    {
        // Clear existing question selections
        DB::table('test_question')->where('test_id', $test->id)->delete();

        if (empty($questionIds)) {
            return;
        }

        $questions = Question::whereIn('id', $questionIds)
            ->with('construct.cluster')
            ->get();

        // Test-specific: construct_id -> cluster_id for this test (from test_cluster_construct)
        $constructToCluster = DB::table('test_cluster_construct')
            ->where('test_id', $test->id)
            ->get()
            ->keyBy('construct_id')
            ->map(fn ($row) => $row->cluster_id)
            ->all();

        $testQuestions = [];
        $orderNo = 1;

        foreach ($questionIds as $questionId) {
            $question = $questions->firstWhere('id', $questionId);
            if (!$question) {
                continue;
            }

            $clusterId = $constructToCluster[$question->construct_id] ?? null;
            if (!$clusterId && $question->construct && $question->construct->cluster) {
                $clusterId = $question->construct->cluster->id;
            }
            if (!$clusterId) {
                $test->load('clusters');
                foreach ($test->clusters as $cluster) {
                    $cluster->load('constructs');
                    if ($cluster->constructs->contains('id', $question->construct_id)) {
                        $clusterId = $cluster->id;
                        break;
                    }
                }
            }

            $testQuestions[] = [
                'test_id' => $test->id,
                'question_id' => $questionId,
                'cluster_id' => $clusterId,
                'order_no' => $orderNo++,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($testQuestions)) {
            DB::table('test_question')->insert($testQuestions);
        }
    }

    /**
     * Download Excel template for test questions
     * Template includes prefilled cluster and construct names based on age_group_id
     */
    public function downloadQuestionsTemplate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'age_group_id' => 'nullable|exists:age_groups,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $ageGroupId = $request->input('age_group_id');
            
            $fileName = 'test-questions-template-';
            if ($ageGroupId) {
                $fileName .= 'age-group-' . $ageGroupId . '-';
            }
            $fileName .= now()->format('Y-m-d_His') . '.xlsx';

            return Excel::download(
                new TestQuestionsTemplateExport($ageGroupId),
                $fileName
            );
        } catch (\Exception $e) {
            \Log::error('Test questions template export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import questions from Excel file for a test
     * Creates questions and attaches them to the test
     */
    public function importQuestions(Request $request, string $id)
    {
        $test = Test::find($id);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        try {
            $file = $request->file('file');
            $ageGroupId = $test->age_group_id;

            // Create import instance
            $import = new TestQuestionsImport($test->id, $ageGroupId, $test->source ?? 'SC Pro');

            // Import the file
            Excel::import($import, $file);

            // Get import statistics
            $stats = $import->getStats();
            $createdQuestions = $stats['created_questions'] ?? [];

            // Attach created questions to the test
            if (!empty($createdQuestions)) {
                $testQuestions = [];
                $orderNo = 1;

                // Get existing max order_no to continue from there
                $maxOrderNo = DB::table('test_question')
                    ->where('test_id', $test->id)
                    ->max('order_no') ?? 0;
                $orderNo = $maxOrderNo + 1;

                foreach ($createdQuestions as $item) {
                    $question = $item['question'];
                    $clusterId = $item['cluster_id'];

                    // Check if question already exists in test
                    $exists = DB::table('test_question')
                        ->where('test_id', $test->id)
                        ->where('question_id', $question->id)
                        ->exists();

                    if (!$exists) {
                        $testQuestions[] = [
                            'test_id' => $test->id,
                            'question_id' => $question->id,
                            'cluster_id' => $clusterId,
                            'order_no' => $orderNo++,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($testQuestions)) {
                    DB::table('test_question')->insert($testQuestions);
                }
            }

            // Reload test with questions
            $test->load('selectedQuestions');

            // Prepare response
            $response = [
                'status' => true,
                'message' => 'Questions imported successfully',
                'data' => [
                    'test_id' => $test->id,
                    'success_count' => $stats['success'],
                    'failure_count' => $stats['failures'],
                    'total_processed' => $stats['success'] + $stats['failures'],
                    'questions_attached' => count($createdQuestions),
                    'selected_questions_count' => $test->selectedQuestions->count(),
                ],
            ];

            // Add failure details if any
            if ($stats['failures'] > 0) {
                $response['errors'] = $stats['errors'];
                $response['message'] = 'Import completed with some errors';
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            \Log::error('Test questions import failed', [
                'test_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to import questions: ' . $e->getMessage(),
            ], 500);
        }
    }
}