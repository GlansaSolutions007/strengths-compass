<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Organization::query();

        // Filter by is_active if provided
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Load users count
        $query->withCount('users');

        $organizations = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'data' => $organizations,
            'message' => 'Organizations fetched successfully'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:organizations,email',
            'contact_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $organization = Organization::create($request->only([
            'name', 'email', 'contact_number', 'address', 'city', 
            'state', 'country', 'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Organization created successfully',
            'data' => $organization
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $organization = Organization::withCount('users')->find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $organization,
            'message' => 'Organization fetched successfully'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:organizations,email,' . $organization->id,
            'contact_number' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $organization->update($request->only([
            'name', 'email', 'contact_number', 'address', 'city', 
            'state', 'country', 'is_active'
        ]));

        $organization->refresh();

        return response()->json([
            'status' => true,
            'message' => 'Organization updated successfully',
            'data' => $organization
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        // Check if organization has users
        if ($organization->users()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete organization with existing users. Please remove or reassign users first.'
            ], 422);
        }

        $organization->delete();

        return response()->json([
            'status' => true,
            'message' => 'Organization deleted successfully'
        ], 200);
    }

    /**
     * Toggle active status
     */
    public function toggleActive(string $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        $organization->is_active = !$organization->is_active;
        $organization->save();

        return response()->json([
            'status' => true,
            'message' => 'Organization status updated successfully',
            'data' => $organization
        ], 200);
    }

    /**
     * Get all users for an organization
     */
    public function getUsers(string $id)
    {
        $organization = Organization::with('users')->find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'organization' => $organization,
                'users' => $organization->users
            ],
            'message' => 'Organization users fetched successfully'
        ], 200);
    }
}

