<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Test;
use App\Models\Cluster;
use App\Models\QuestionsModel as Question;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Test::with('clusters');

        // Filter by is_active if provided
        if (request()->has('is_active')) {
            $query->where('is_active', filter_var(request('is_active'), FILTER_VALIDATE_BOOLEAN));
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
    public function store(Request $request)
    {
        // Debug: Log the request method and data
        \Log::info('Test Store Method Called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'cluster_ids' => 'sometimes|array',
            'cluster_ids.*' => 'exists:clusters,id',
            'clusters' => 'sometimes|array',
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

        try {
            $test = Test::create($request->only(['title', 'description', 'is_active']));

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

            // Auto-generate questions if clusters are attached
            if ($test->clusters->count() > 0) {
                $this->generateQuestionSelectionInternal($test);
            }

            // Reload test with questions to include in response
            $test->load('selectedQuestions');

            return response()->json([
                'status' => true,
                'message' => 'Test created successfully',
                'data' => $test,
                'selected_questions_count' => $test->selectedQuestions->count()
            ], 201);
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
        $test = Test::with(['clusters', 'clusters.constructs'])->find($id);

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
            'is_active' => 'sometimes|boolean',
            'clusters' => 'sometimes|array',
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

        $test->update($request->only(['title', 'description', 'is_active']));

        // Sync clusters with category counts if provided
        if ($request->has('clusters')) {
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

        return response()->json([
            'status' => true,
            'message' => 'Test updated successfully',
            'data' => $test
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

            // Get all active questions from this cluster
            $availableQuestions = Question::whereHas('construct', function ($query) use ($cluster) {
                $query->where('cluster_id', $cluster->id);
            })->where('is_active', true)->get();

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
}
