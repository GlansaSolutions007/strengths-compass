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
    public function index()
    {
        $clusters = Cluster::with('constructs')->get();

        return response()->json([
            'status' => true,
            'data' => $clusters,
            'message' => 'Clusters fetched successfully'
        ], 200);
    }

    // ✅ Get Single Cluster
    public function show($id)
    {
        $cluster = Cluster::with('constructs')->find($id);

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
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cluster = Cluster::create($request->only('name', 'description'));

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
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cluster->update($request->only('name', 'description'));

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
}
