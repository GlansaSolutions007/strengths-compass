<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List users (admin only) with pagination.
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();

        if (!$currentUser || $currentUser->role !== 'admin') {
            return response()->json([
                'data' => [],
                'status' => 403,
                'message' => 'Forbidden',
            ], 403);
        }

        $perPage = (int) $request->query('per_page', 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $users = User::orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $users,
            'status' => 200,
            'message' => 'Users fetched successfully',
        ], 200);
    }

    /**
     * Show a single user by id.
     * Admins can view any user; non-admins can only view themselves.
     */
    public function show(Request $request, int $id)
    {
        $currentUser = $request->user();

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        $isAdmin = $currentUser && $currentUser->role === 'admin';
        $isSelf = $currentUser && $currentUser->id === $user->id;
        if (!$isAdmin && !$isSelf) {
            return response()->json([
                'data' => [],
                'status' => 403,
                'message' => 'Forbidden',
            ], 403);
        }

        return response()->json([
            'data' => $user,
            'status' => 200,
            'message' => 'User fetched successfully',
        ], 200);
    }

    /**
     * Update a user.
     * Admins can update any user and any field; non-admins can update only their own profile
     * and only limited fields (name, gender, age, contact, password).
     */
    public function update(Request $request, int $id)
    {
        $currentUser = $request->user();
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        $isAdmin = $currentUser && $currentUser->role === 'admin';
        $isSelf = $currentUser && $currentUser->id === $user->id;
        if (!$isAdmin && !$isSelf) {
            return response()->json([
                'data' => [],
                'status' => 403,
                'message' => 'Forbidden',
            ], 403);
        }

        // Validation rules differ for admin vs self-update
        if ($isAdmin) {
            $rules = [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'sometimes|nullable|string|min:8|confirmed',
                'role' => 'sometimes|required|in:admin,user',
                'gender' => 'sometimes|nullable|in:male,female,other,prefer_not_to_say',
                'age' => 'sometimes|nullable|integer|min:1|max:150',
                'contact' => 'sometimes|nullable|string|max:255',
            ];
        } else {
            $rules = [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'prohibited',
                'role' => 'prohibited',
                'password' => 'sometimes|nullable|string|min:8|confirmed',
                'gender' => 'sometimes|nullable|in:male,female,other,prefer_not_to_say',
                'age' => 'sometimes|nullable|integer|min:1|max:150',
                'contact' => 'sometimes|nullable|string|max:255',
            ];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updatable = $request->only([
            'name', 'email', 'role', 'gender', 'age', 'contact'
        ]);

        if ($request->filled('password')) {
            $updatable['password'] = Hash::make($request->password);
        }

        $user->update($updatable);

        return response()->json([
            'data' => $user,
            'status' => 200,
            'message' => 'User updated successfully',
        ], 200);
    }
}


