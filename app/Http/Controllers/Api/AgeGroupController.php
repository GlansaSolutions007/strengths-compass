<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AgeGroup;
use App\Models\Cluster;
use App\Models\Construct;
use Illuminate\Support\Facades\Validator;

class AgeGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = AgeGroup::query();

        // Filter by is_active if provided
        if (request()->has('is_active')) {
            $query->where('is_active', filter_var(request('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $ageGroups = $query->orderBy('from')->get();

        return response()->json([
            'status' => true,
            'data' => $ageGroups,
            'message' => 'Age groups fetched successfully'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'from' => 'required|integer|min:0',
            'to' => 'required|integer|min:0|gte:from',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $ageGroup = AgeGroup::create($request->only([
            'name', 'from', 'to', 'description', 'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Age group created successfully',
            'data' => $ageGroup
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ageGroup = AgeGroup::find($id);

        if (!$ageGroup) {
            return response()->json([
                'status' => false,
                'message' => 'Age group not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $ageGroup,
            'message' => 'Age group fetched successfully'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ageGroup = AgeGroup::find($id);

        if (!$ageGroup) {
            return response()->json([
                'status' => false,
                'message' => 'Age group not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'from' => 'sometimes|required|integer|min:0',
            'to' => 'sometimes|required|integer|min:0|gte:from',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $ageGroup->update($request->only([
            'name', 'from', 'to', 'description', 'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Age group updated successfully',
            'data' => $ageGroup
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ageGroup = AgeGroup::find($id);

        if (!$ageGroup) {
            return response()->json([
                'status' => false,
                'message' => 'Age group not found'
            ], 404);
        }

        $ageGroup->delete();

        return response()->json([
            'status' => true,
            'message' => 'Age group deleted successfully'
        ], 200);
    }

    /**
     * Toggle the is_active status of an age group
     * Admin only when authentication is enabled
     */
    public function toggleActive(Request $request, string $id)
    {
        $currentUser = $request->user();
        $hasAuthToken = $request->bearerToken() || $request->hasHeader('Authorization');

        // Check admin access if authenticated
        if ($hasAuthToken && $currentUser && $currentUser->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden - Admin access required'
            ], 403);
        }

        $ageGroup = AgeGroup::find($id);

        if (!$ageGroup) {
            return response()->json([
                'status' => false,
                'message' => 'Age group not found'
            ], 404);
        }

        // Toggle is_active
        $ageGroup->is_active = !$ageGroup->is_active;
        $ageGroup->save();

        return response()->json([
            'status' => true,
            'message' => 'Age group active status toggled successfully',
            'data' => $ageGroup
        ], 200);
    }

    /**
     * Clone clusters and constructs from a source age group to target age group(s)
     * Admin only when authentication is enabled
     * 
     * Source age group can be specified in:
     * 1. URL path parameter: /age-groups/{id}/clone-clusters-constructs
     * 2. Request body: { "source_age_group_id": 1, ... }
     * If both are provided, request body takes precedence
     */
    public function cloneClustersAndConstructs(Request $request, string $sourceAgeGroupId = null)
    {
        $currentUser = $request->user();
        $hasAuthToken = $request->bearerToken() || $request->hasHeader('Authorization');

        // Check admin access if authenticated
        if ($hasAuthToken && $currentUser && $currentUser->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden - Admin access required'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'source_age_group_id' => 'sometimes|required|exists:age_groups,id',
            'target_age_group_ids' => 'required|array|min:1',
            'target_age_group_ids.*' => 'required|exists:age_groups,id',
            'replace_existing' => 'sometimes|boolean', // If true, delete existing clusters/constructs before cloning
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        // Get source age group ID from request body (preferred) or URL parameter
        $sourceAgeGroupId = $request->input('source_age_group_id', $sourceAgeGroupId);

        if (!$sourceAgeGroupId) {
            return response()->json([
                'status' => false,
                'message' => 'Source age group ID is required. Provide it in the URL path or request body as "source_age_group_id"'
            ], 422);
        }

        // Find source age group with clusters and constructs
        $sourceAgeGroup = AgeGroup::with(['clusters.constructs'])->find($sourceAgeGroupId);

        if (!$sourceAgeGroup) {
            return response()->json([
                'status' => false,
                'message' => 'Source age group not found'
            ], 404);
        }

        $targetAgeGroupIds = $request->input('target_age_group_ids');
        $replaceExisting = $request->input('replace_existing', false);

        $results = [];
        $totalClustersCloned = 0;
        $totalConstructsCloned = 0;

        foreach ($targetAgeGroupIds as $targetAgeGroupId) {
            // Skip if trying to clone to itself
            if ($sourceAgeGroupId == $targetAgeGroupId) {
                $results[$targetAgeGroupId] = [
                    'status' => 'skipped',
                    'message' => 'Cannot clone to the same age group'
                ];
                continue;
            }

            $targetAgeGroup = AgeGroup::find($targetAgeGroupId);
            if (!$targetAgeGroup) {
                $results[$targetAgeGroupId] = [
                    'status' => 'error',
                    'message' => 'Target age group not found'
                ];
                continue;
            }

            // If replace_existing is true, delete existing clusters and constructs
            if ($replaceExisting) {
                $existingClusters = Cluster::where('age_group_id', $targetAgeGroupId)->get();
                foreach ($existingClusters as $cluster) {
                    Construct::where('cluster_id', $cluster->id)->delete();
                    $cluster->delete();
                }
            }

            $clustersCloned = 0;
            $constructsCloned = 0;
            $clusterMapping = []; // Map old cluster IDs to new cluster IDs

            // Clone clusters and their constructs
            foreach ($sourceAgeGroup->clusters as $sourceCluster) {
                // Clone the cluster
                $newCluster = $sourceCluster->replicate();
                $newCluster->age_group_id = $targetAgeGroupId;
                $newCluster->save();
                $clustersCloned++;
                $totalClustersCloned++;

                // Store mapping for constructs
                $clusterMapping[$sourceCluster->id] = $newCluster->id;

                // Clone constructs for this cluster
                foreach ($sourceCluster->constructs as $sourceConstruct) {
                    $newConstruct = $sourceConstruct->replicate();
                    $newConstruct->cluster_id = $newCluster->id;
                    $newConstruct->age_group_id = $targetAgeGroupId;
                    $newConstruct->save();
                    $constructsCloned++;
                    $totalConstructsCloned++;
                }
            }

            $results[$targetAgeGroupId] = [
                'status' => 'success',
                'age_group_name' => $targetAgeGroup->name,
                'clusters_cloned' => $clustersCloned,
                'constructs_cloned' => $constructsCloned
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Clusters and constructs cloned successfully',
            'data' => [
                'source_age_group' => [
                    'id' => $sourceAgeGroup->id,
                    'name' => $sourceAgeGroup->name,
                    'clusters_count' => $sourceAgeGroup->clusters->count(),
                    'constructs_count' => $sourceAgeGroup->clusters->sum(function($cluster) {
                        return $cluster->constructs->count();
                    })
                ],
                'targets' => $results,
                'summary' => [
                    'total_targets' => count($targetAgeGroupIds),
                    'total_clusters_cloned' => $totalClustersCloned,
                    'total_constructs_cloned' => $totalConstructsCloned
                ]
            ]
        ], 200);
    }
}
