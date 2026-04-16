<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * GET /roles
     * Display a listing of all roles.
     */
    public function index(Request $request)
    {
        $query = Role::query();

        if ($request->has('isActive')) {
            $query->where('isActive', filter_var($request->isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $roles = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'data' => $roles,
            'message' => 'Roles fetched successfully'
        ], 200);
    }

    /**
     * POST /roles
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:roles,name',
            'isActive' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $role = Role::create($request->only(['name', 'isActive']));

        return response()->json([
            'status'  => true,
            'message' => 'Role created successfully',
            'data'    => $role
        ], 201);
    }

    /**
     * GET /roles/{id}
     * Display the specified role.
     */
    public function show(string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status'  => false,
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $role,
            'message' => 'Role fetched successfully'
        ], 200);
    }

    /**
     * PUT /roles/{id}
     * Update the specified role.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status'  => false,
                'message' => 'Role not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'isActive' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $role->update($request->only(['name', 'isActive']));
        $role->refresh();

        return response()->json([
            'status'  => true,
            'message' => 'Role updated successfully',
            'data'    => $role
        ], 200);
    }

    /**
     * DELETE /roles/{id}
     * Remove the specified role.
     */
    public function destroy(string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status'  => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Role deleted successfully'
        ], 200);
    }

    /**
     * PATCH /roles/{id}/toggle-active
     * Toggle the isActive status of a role.
     */
    public function toggleActive(string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status'  => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->isActive = !$role->isActive;
        $role->save();

        return response()->json([
            'status'  => true,
            'message' => 'Role status updated successfully',
            'data'    => $role
        ], 200);
    }
}
