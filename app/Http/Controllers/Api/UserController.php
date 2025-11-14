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
     * List users with pagination.
     * Admin only when authentication is enabled.
     * Currently public for development/testing.
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // Check if authentication is enabled and user is not admin
        // If auth middleware is not applied, $currentUser will be null and this check is skipped
        if ($currentUser && $currentUser->role !== 'admin') {
            return response()->json([
                'user' => [],
                'status' => 403,
                'message' => 'Forbidden - Admin access required',
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
            'users' => $users,
            'status' => 200,
            'message' => 'Users fetched successfully',
        ], 200);
    }

    /**
     * Show a single user by id.
     * Admins can view any user; non-admins can only view themselves.
     * Currently public for development/testing.
     */
    public function show(Request $request, int $id)
    {
        $currentUser = $request->user();

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'user' => [],
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        // Only enforce access control if user is authenticated
        // If auth middleware is not applied, allow public access
        if ($currentUser) {
            $isAdmin = $currentUser->role === 'admin';
            $isSelf = $currentUser->id === $user->id;
            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'user' => [],
                    'status' => 403,
                    'message' => 'Forbidden - You can only view your own profile',
                ], 403);
            }
        }

        return response()->json([
            'user' => $user,
            'status' => 200,
            'message' => 'User fetched successfully',
        ], 200);
    }

    /**
     * Update a user.
     * Different validation rules based on user role (admin vs regular user).
     * Admins can update any user; regular users can only update their own profile.
     */
    public function update(Request $request, int $id)
    {
        $currentUser = $request->user();
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'user' => [],
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        // Access control: Allow public access if no authentication token is present
        // If authenticated, enforce: admins can update anyone, users can only update themselves
        $hasAuthToken = $request->bearerToken() || $request->hasHeader('Authorization');
        
        if ($hasAuthToken && $currentUser) {
            // User is authenticated - enforce access control
            $isAdmin = $currentUser->role === 'admin';
            $isSelf = $currentUser->id === $user->id;
            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'user' => [],
                    'status' => 403,
                    'message' => 'Forbidden - You can only update your own profile',
                ], 403);
            }
        }
        // If no auth token, allow public access (for development/testing)

        // Determine validation rules based on the user being updated (not the current user)
        $isUpdatingAdmin = $user->role === 'admin';
        
        // Validation rules differ for admin vs regular user
        if ($isUpdatingAdmin) {
            // Admin user fields - all optional for partial updates
            $rules = [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'sometimes|nullable|string|min:8|confirmed',
                'role' => 'sometimes|in:admin,user',
            ];
        } else {
            // Regular user fields - all optional for partial updates
            $rules = [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'whatsapp_number' => 'sometimes|string|max:20',
                'city' => 'sometimes|string|max:255',
                'state' => 'sometimes|string|max:255',
                'country' => 'sometimes|string|max:255',
                'profession' => 'sometimes|string|max:255',
                'gender' => 'sometimes|in:male,female,other,prefer_not_to_say',
                'age' => 'sometimes|integer|min:1|max:150',
                'educational_qualification' => 'sometimes|string|max:255',
                'password' => 'sometimes|nullable|string|min:8|confirmed',
            ];

            // Regular users cannot change their email or role
            if (!$currentUser || $currentUser->role !== 'admin') {
                $rules['email'] = 'prohibited';
                $rules['role'] = 'prohibited';
            } else {
                // Admins can change email and role for regular users
                $rules['email'] = 'sometimes|string|email|max:255|unique:users,email,' . $user->id;
                $rules['role'] = 'sometimes|in:admin,user';
            }
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

        // Prepare update data based on user role
        if ($isUpdatingAdmin) {
            // Admin fields
            $updatable = $request->only(['name', 'email', 'role']);
        } else {
            // Regular user fields
            $updatable = $request->only([
                'first_name',
                'last_name',
                'whatsapp_number',
                'city',
                'state',
                'country',
                'profession',
                'gender',
                'age',
                'educational_qualification',
            ]);

            // Admins can also update email and role for regular users
            if ($currentUser && $currentUser->role === 'admin') {
                if ($request->has('email')) {
                    $updatable['email'] = $request->email;
                }
                if ($request->has('role')) {
                    $updatable['role'] = $request->role;
                }
            }

            // Update name field from first_name and last_name
            if ($request->has('first_name') || $request->has('last_name')) {
                $firstName = $request->input('first_name', $user->first_name);
                $lastName = $request->input('last_name', $user->last_name);
                $updatable['name'] = trim($firstName . ' ' . $lastName);
            }
        }

        // Handle password update
        if ($request->filled('password')) {
            $updatable['password'] = Hash::make($request->password);
        }

        $user->update($updatable);
        $user->refresh(); // Refresh to get updated data

        return response()->json([
            'user' => $user,
            'status' => 200,
            'message' => 'User updated successfully',
        ], 200);
    }

    /**
     * Delete a user.
     * Only admins can delete users.
     */
    public function destroy(Request $request, int $id)
    {
        $currentUser = $request->user();
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'user' => [],
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        // Only enforce access control if user is authenticated
        // If auth middleware is not applied, allow public access (for development)
        if ($currentUser) {
            $isAdmin = $currentUser->role === 'admin';
            if (!$isAdmin) {
                return response()->json([
                    'user' => [],
                    'status' => 403,
                    'message' => 'Forbidden - Only admins can delete users',
                ], 403);
            }

            // Prevent admin from deleting themselves
            if ($currentUser->id === $user->id) {
                return response()->json([
                    'user' => [],
                    'status' => 403,
                    'message' => 'Forbidden - You cannot delete your own account',
                ], 403);
            }
        }

        $user->delete();

        return response()->json([
            'user' => [],
            'status' => 200,
            'message' => 'User deleted successfully',
        ], 200);
    }
}


