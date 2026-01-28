<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cluster;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BehaviorContentExport;
use App\Imports\BehaviorContentImport;

class ClusterController extends Controller
{
    // ✅ Get All Clusters
    public function index(Request $request)
    {
        $query = Cluster::with(['constructs', 'ageGroup']);

        // Filter by age_group_id if provided in request
        if ($request->has('age_group_id')) {
            $query->where('age_group_id', $request->age_group_id);
        }

        $clusters = $query->get();

        return response()->json([
            'status' => true,
            'data' => $clusters,
            'message' => 'Clusters fetched successfully'
        ], 200);
    }

    // ✅ Get Single Cluster
    public function show($id)
    {
        $cluster = Cluster::with(['constructs', 'ageGroup'])->find($id);

        if (!$cluster) {
            return response()->json([
                'status' => false,
                'message' => 'Cluster not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $cluster
        ], 200);
    }

    // ✅ Create New Cluster
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'short_code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'area' => 'nullable|string|max:255',
            'age_group_id' => 'nullable|exists:age_groups,id',
            'high_behaviour' => 'nullable|string',
            'medium_behaviour' => 'nullable|string',
            'low_behaviour' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cluster = Cluster::create($request->only('name', 'short_code', 'description', 'area', 'age_group_id', 'high_behaviour', 'medium_behaviour', 'low_behaviour'));

        return response()->json([
            'status' => true,
            'message' => 'Cluster created successfully',
            'data' => $cluster
        ], 201);
    }

    // ✅ Update Cluster
    public function update(Request $request, $id)
    {
        $cluster = Cluster::find($id);

        if (!$cluster) {
            return response()->json([
                'status' => false,
                'message' => 'Cluster not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'short_code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'area' => 'nullable|string|max:255',
            'age_group_id' => 'nullable|exists:age_groups,id',
            'high_behaviour' => 'nullable|string',
            'medium_behaviour' => 'nullable|string',
            'low_behaviour' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cluster->update($request->only('name', 'short_code', 'description', 'area', 'age_group_id', 'high_behaviour', 'medium_behaviour', 'low_behaviour'));

        return response()->json([
            'status' => true,
            'message' => 'Cluster updated successfully',
            'data' => $cluster
        ]);
    }

    // ✅ Delete Cluster
    public function destroy($id)
    {
        $cluster = Cluster::find($id);

        if (!$cluster) {
            return response()->json([
                'status' => false,
                'message' => 'Cluster not found'
            ], 404);
        }

        // Detach any many-to-many relationships before deleting (if pivot table exists)
        if (Schema::hasTable('test_cluster')) {
            $cluster->tests()->detach();
        }

        $cluster->delete();

        return response()->json([
            'status' => true,
            'message' => 'Cluster deleted successfully'
        ]);
    }

    // ✅ Toggle Active Status
    public function toggleActive(Request $request, $id)
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

        $cluster = Cluster::find($id);

        if (!$cluster) {
            return response()->json([
                'status' => false,
                'message' => 'Cluster not found'
            ], 404);
        }

        // Toggle is_active
        $cluster->is_active = !$cluster->is_active;
        $cluster->save();

        return response()->json([
            'status' => true,
            'message' => 'Cluster active status toggled successfully',
            'data' => $cluster
        ], 200);
    }

    /**
     * Download Excel template with clusters and constructs behavior content
     * 
     * @param Request $request
     *   - age_group_id: Filter by age group (optional)
     *   - type: 'clusters' or 'constructs' (optional, exports both if not provided)
     */
    public function downloadBehaviorContentExcel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'age_group_id' => 'nullable|exists:age_groups,id',
                'type' => 'nullable|in:clusters,constructs',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $ageGroupId = $request->input('age_group_id');
            $type = $request->input('type'); // 'clusters', 'constructs', or null
            
            $fileName = 'behavior-content-';
            if ($type) {
                $fileName .= $type . '-';
            }
            if ($ageGroupId) {
                $fileName .= 'age-group-' . $ageGroupId . '-';
            }
            $fileName .= now()->format('Y-m-d_His') . '.xlsx';

            return Excel::download(
                new \App\Exports\BehaviorContentExport($ageGroupId, $type),
                $fileName
            );
        } catch (\Exception $e) {
            \Log::error('Behavior content Excel export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate Excel file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload Excel file to update behavior content for clusters and constructs
     * 
     * @param Request $request
     *   - file: Excel file (required)
     *   - type: 'clusters' or 'constructs' (optional, auto-detects if not provided)
     *   - age_group_id: Filter by age group (optional, validates if provided)
     */
    public function uploadBehaviorContentExcel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
                'type' => 'nullable|in:clusters,constructs',
                'age_group_id' => 'nullable|exists:age_groups,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('file');
            $type = $request->input('type'); // 'clusters' or 'constructs' or null
            $ageGroupId = $request->input('age_group_id');

            // Import the Excel file with specified type
            $import = new \App\Imports\BehaviorContentImport($type);
            
            // If age_group_id is provided, we'll validate it during import
            // The import classes already validate age_group_id matches
            Excel::import($import, $file);

            // Get import statistics
            $stats = $import->getStats();

            return response()->json([
                'status' => true,
                'message' => 'Behavior content updated successfully',
                'data' => [
                    'type' => $type ?? 'auto-detected',
                    'age_group_id' => $ageGroupId,
                    'updated_clusters' => $stats['updated_clusters'],
                    'updated_constructs' => $stats['updated_constructs'],
                    'total_success' => $stats['success'],
                    'total_failures' => $stats['failures'],
                    'errors' => $stats['errors'],
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Behavior content Excel import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to import Excel file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
