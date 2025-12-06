<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AgeGroup;
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
}
