<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Language::query();

            // Filter by is_active if provided
            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            // Filter by code if provided
            if ($request->has('code')) {
                $query->where('code', $request->code);
            }

            $languages = $query->orderBy('name')->get();

            return response()->json([
                'status' => true,
                'data' => $languages,
                'message' => 'Languages fetched successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching languages: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:languages,code',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        $language = Language::create([
            'name' => $request->name,
            'code' => $request->code,
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'status' => true,
            'data' => $language,
            'message' => 'Language created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $language = Language::find($id);

        if (!$language) {
            return response()->json([
                'status' => false,
                'data' => [],
                'message' => 'Language not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $language,
            'message' => 'Language fetched successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $language = Language::find($id);

        if (!$language) {
            return response()->json([
                'status' => false,
                'data' => [],
                'message' => 'Language not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:10|unique:languages,code,' . $id,
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        $language->update($request->only(['name', 'code', 'is_active']));

        return response()->json([
            'status' => true,
            'data' => $language,
            'message' => 'Language updated successfully',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $language = Language::find($id);

        if (!$language) {
            return response()->json([
                'status' => false,
                'data' => [],
                'message' => 'Language not found',
            ], 404);
        }

        $language->delete();

        return response()->json([
            'status' => true,
            'data' => [],
            'message' => 'Language deleted successfully',
        ], 200);
    }

    /**
     * Toggle the is_active status of a language
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

        $language = Language::find($id);

        if (!$language) {
            return response()->json([
                'status' => false,
                'message' => 'Language not found'
            ], 404);
        }

        // Toggle is_active
        $language->is_active = !$language->is_active;
        $language->save();

        return response()->json([
            'status' => true,
            'message' => 'Language active status toggled successfully',
            'data' => $language
        ], 200);
    }
}

