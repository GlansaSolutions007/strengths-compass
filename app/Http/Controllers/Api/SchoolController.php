<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = School::query();

        // Filter by is_active if provided
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Load users count
        $query->withCount('users');

        $schools = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'data' => $schools,
            'message' => 'Schools fetched successfully'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'shortcode' => 'nullable|string|max:50|unique:schools,shortcode',
            'registration_no' => 'nullable|string|max:255|unique:schools,registration_no',
            'email' => 'nullable|email|max:255|unique:schools,email',
            'contact_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'principal_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $school = School::create($request->only([
            'name', 'shortcode', 'registration_no', 'email', 'contact_number', 
            'address', 'city', 'state', 'country', 'principal_name', 'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'School created successfully',
            'data' => $school
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $school = School::withCount('users')->find($id);

        if (!$school) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $school,
            'message' => 'School fetched successfully'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $school = School::find($id);

        if (!$school) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'shortcode' => 'sometimes|nullable|string|max:50|unique:schools,shortcode,' . $school->id,
            'registration_no' => 'sometimes|nullable|string|max:255|unique:schools,registration_no,' . $school->id,
            'email' => 'sometimes|nullable|email|max:255|unique:schools,email,' . $school->id,
            'contact_number' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'principal_name' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $school->update($request->only([
            'name', 'shortcode', 'registration_no', 'email', 'contact_number', 
            'address', 'city', 'state', 'country', 'principal_name', 'is_active'
        ]));

        $school->refresh();

        return response()->json([
            'status' => true,
            'message' => 'School updated successfully',
            'data' => $school
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $school = School::find($id);

        if (!$school) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        // Check if school has users
        if ($school->users()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete school with existing users. Please remove or reassign users first.'
            ], 422);
        }

        $school->delete();

        return response()->json([
            'status' => true,
            'message' => 'School deleted successfully'
        ], 200);
    }

    /**
     * Toggle active status
     */
    public function toggleActive(string $id)
    {
        $school = School::find($id);

        if (!$school) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        $school->is_active = !$school->is_active;
        $school->save();

        return response()->json([
            'status' => true,
            'message' => 'School status updated successfully',
            'data' => $school
        ], 200);
    }

    /**
     * Get all users for a school
     */
    public function getUsers(string $id)
    {
        $school = School::with('users')->find($id);

        if (!$school) {
            return response()->json([
                'status' => false,
                'message' => 'School not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'school' => $school,
                'users' => $school->users
            ],
            'message' => 'School users fetched successfully'
        ], 200);
    }
}

