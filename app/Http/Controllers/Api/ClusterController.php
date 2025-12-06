<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cluster;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class ClusterController extends Controller
{
    // ✅ Get All Clusters
    public function index(Request $request)
    {
        $query = Cluster::with(['constructs', 'ageGroup']);

        // Get age_group_id from request or session
        $ageGroupId = $request->input('age_group_id');
        
        // If provided in request, store it in session
        if ($ageGroupId !== null) {
            session(['selected_age_group_id' => $ageGroupId]);
        } else {
            // Otherwise, get from session
            $ageGroupId = session('selected_age_group_id');
        }

        // Filter by age_group_id if available
        if ($ageGroupId !== null) {
            $query->where('age_group_id', $ageGroupId);
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

        $cluster = Cluster::create($request->only('name', 'short_code', 'description', 'age_group_id', 'high_behaviour', 'medium_behaviour', 'low_behaviour'));

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

        $cluster->update($request->only('name', 'short_code', 'description', 'age_group_id', 'high_behaviour', 'medium_behaviour', 'low_behaviour'));

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
}
