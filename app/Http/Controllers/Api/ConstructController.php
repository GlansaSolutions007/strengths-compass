<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Construct;
use App\Models\Cluster;
use Illuminate\Support\Facades\Validator;

class ConstructController extends Controller
{
    // ✅ Get All Constructs (optionally filtered by cluster_id)
    public function index(Request $request)
    {
        $query = Construct::with('cluster');

        // Filter by cluster_id if provided
        if ($request->has('cluster_id')) {
            $query->where('cluster_id', $request->cluster_id);
        }

        $constructs = $query->orderBy('display_order')->get();

        return response()->json([
            'status' => true,
            'data' => $constructs
        ], 200);
    }

    // ✅ Get Constructs by Cluster ID
    public function getByCluster($clusterId)
    {
        $cluster = Cluster::find($clusterId);

        if (!$cluster) {
            return response()->json([
                'status' => false,
                'message' => 'Cluster not found'
            ], 404);
        }

        $constructs = Construct::where('cluster_id', $clusterId)
            ->with('cluster')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $constructs
        ], 200);
    }

    // ✅ Get Single Construct
    public function show($id)
    {
        $construct = Construct::with('cluster')->find($id);

        if (!$construct) {
            return response()->json([
                'status' => false,
                'message' => 'Construct not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $construct
        ], 200);
    }

    // ✅ Create New Construct
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cluster_id' => 'required|exists:clusters,id',
            'name' => 'required|string|max:255',
            'short_code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'definition' => 'nullable|string',
            'high_behavior' => 'nullable|string',
            'medium_behavior' => 'nullable|string',
            'low_behavior' => 'nullable|string',
            'benefits' => 'nullable|string',
            'risks' => 'nullable|string',
            'coaching_applications' => 'nullable|string',
            'case_example' => 'nullable|string',
            'display_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $construct = Construct::create($request->only([
            'cluster_id',
            'name',
            'short_code',
            'description',
            'definition',
            'high_behavior',
            'medium_behavior',
            'low_behavior',
            'benefits',
            'risks',
            'coaching_applications',
            'case_example',
            'display_order'
        ]));

        $construct->load('cluster');

        return response()->json([
            'status' => true,
            'message' => 'Construct created successfully',
            'data' => $construct
        ], 201);
    }

    // ✅ Update Construct
    public function update(Request $request, $id)
    {
        $construct = Construct::find($id);

        if (!$construct) {
            return response()->json([
                'status' => false,
                'message' => 'Construct not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'cluster_id' => 'sometimes|required|exists:clusters,id',
            'name' => 'sometimes|required|string|max:255',
            'short_code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'definition' => 'nullable|string',
            'high_behavior' => 'nullable|string',
            'medium_behavior' => 'nullable|string',
            'low_behavior' => 'nullable|string',
            'benefits' => 'nullable|string',
            'risks' => 'nullable|string',
            'coaching_applications' => 'nullable|string',
            'case_example' => 'nullable|string',
            'display_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $construct->update($request->only([
            'cluster_id',
            'name',
            'short_code',
            'description',
            'definition',
            'high_behavior',
            'medium_behavior',
            'low_behavior',
            'benefits',
            'risks',
            'coaching_applications',
            'case_example',
            'display_order'
        ]));

        $construct->load('cluster');

        return response()->json([
            'status' => true,
            'message' => 'Construct updated successfully',
            'data' => $construct
        ], 200);
    }

    // ✅ Delete Construct
    public function destroy($id)
    {
        $construct = Construct::find($id);

        if (!$construct) {
            return response()->json([
                'status' => false,
                'message' => 'Construct not found'
            ], 404);
        }

        $construct->delete();

        return response()->json([
            'status' => true,
            'message' => 'Construct deleted successfully'
        ], 200);
    }
}

