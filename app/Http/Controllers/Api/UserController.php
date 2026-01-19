<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AgeGroup;
use App\Models\School;
use App\Models\Organization;
use App\Imports\SchoolUsersImport;
use App\Imports\OrganizationUsersImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Sanctum\PersonalAccessToken;

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

        // Build query with filters
        $query = User::query();

        // Filter by age_group_id if provided
        $ageGroupId = $request->input('age_group_id');
        if ($ageGroupId !== null) {
            $query->where('age_group_id', $ageGroupId);
        }

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by user_type if provided
        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by school_id if provided
        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        // Filter by organization_id if provided
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        // Load relationships
        $query->with(['ageGroup', 'school', 'organization']);

        // $users = $query->orderByDesc('id')->paginate($perPage);
        $users = $query->orderByDesc('id');

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
        $userType = $user->user_type ?? 'individual';
        
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
                'contact_number' => 'sometimes|string|max:20',
                'city' => 'sometimes|string|max:255',
                'state' => 'sometimes|string|max:255',
                'country' => 'sometimes|string|max:255',
                'profession' => 'sometimes|string|max:255',
                'gender' => 'sometimes|in:male,female,other,prefer_not_to_say',
                'age' => 'sometimes|integer|min:1|max:150',
                'educational_qualification' => 'sometimes|string|max:255',
                'password' => 'sometimes|nullable|string|min:8|confirmed',
                'user_type' => 'sometimes|in:individual,school,organization',
                'school_id' => 'sometimes|nullable|exists:schools,id',
                'organization_id' => 'sometimes|nullable|exists:organizations,id',
            ];

            // Email validation: required for individual, optional for school/organization users
            if ($userType === 'school' || $userType === 'organization') {
                // School/Organization users: email is optional
                if (!$currentUser || $currentUser->role !== 'admin') {
                    $rules['email'] = 'prohibited';
                } else {
                    $rules['email'] = 'sometimes|nullable|string|email|max:255|unique:users,email,' . $user->id;
                }
            } else {
                // Individual users: email is required
                if (!$currentUser || $currentUser->role !== 'admin') {
                    $rules['email'] = 'prohibited';
                } else {
                    $rules['email'] = 'sometimes|string|email|max:255|unique:users,email,' . $user->id;
                }
            }

            // Regular users cannot change their role
            if (!$currentUser || $currentUser->role !== 'admin') {
                $rules['role'] = 'prohibited';
            } else {
                // Admins can change role for regular users
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
                'contact_number',
                'city',
                'state',
                'country',
                'profession',
                'gender',
                'age',
                'educational_qualification',
            ]);

            // Add user_type, school_id, organization_id if provided
            if ($request->has('user_type')) {
                $updatable['user_type'] = $request->user_type;
            }
            if ($request->has('school_id')) {
                $updatable['school_id'] = $request->school_id;
            }
            if ($request->has('organization_id')) {
                $updatable['organization_id'] = $request->organization_id;
            }

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

            // Automatically update age_group_id if age is being updated
            if ($request->has('age') && $request->age) {
                $ageGroupId = $this->getAgeGroupIdByAge($request->age);
                if ($ageGroupId) {
                    $updatable['age_group_id'] = $ageGroupId;
                } else {
                    // If no age group found, set to null
                    $updatable['age_group_id'] = null;
                }
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

    /**
     * Change password for a user (Admin only)
     * Admins can change any user's password without knowing the current password
     */
    public function changePassword(Request $request, int $id)
    {
        // Try to get user from request (if middleware is applied)
        $currentUser = $request->user();

        // If no user from middleware, try to authenticate manually using bearer token
        if (!$currentUser) {
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Please provide a valid token',
                ], 401);
            }

            // Find the token and get the user
            $accessToken = PersonalAccessToken::findToken($token);
            
            if (!$accessToken) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Invalid token',
                ], 401);
            }

            $currentUser = $accessToken->tokenable;
            
            if (!$currentUser) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - User not found',
                ], 401);
            }
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'User not found',
            ], 404);
        }

        if ($currentUser->role !== 'admin') {
            return response()->json([
                'data' => [],
                'status' => 403,
                'message' => 'Forbidden - Admin access required',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'data' => [
                'user' => $user,
            ],
            'status' => 200,
            'message' => 'Password changed successfully',
        ], 200);
    }

    /**
     * Bulk import school users from Excel file
     * Admin only
     */
    public function importSchoolUsers(Request $request)
    {
        // Try to get user from request (if middleware is applied)
        $currentUser = $request->user();

        // If no user from middleware, try to authenticate manually using bearer token
        if (!$currentUser) {
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Please provide a valid token',
                ], 401);
            }

            // Find the token and get the user
            $accessToken = PersonalAccessToken::findToken($token);
            
            if (!$accessToken) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Invalid token',
                ], 401);
            }

            $currentUser = $accessToken->tokenable;
            
            if (!$currentUser) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - User not found',
                ], 401);
            }
        }

        // Check if user is authenticated and is admin
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'data' => [],
                'status' => 403,
                'message' => 'Forbidden - Admin access required',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $schoolId = $request->input('school_id');
        $file = $request->file('file');

        try {
            $import = new SchoolUsersImport($schoolId);
            Excel::import($import, $file);

            $successCount = $import->getSuccessCount();
            $failureCount = $import->getFailureCount();
            $errors = $import->getErrors();

            // Log import results for debugging
            \Log::info('School users import completed', [
                'school_id' => $schoolId,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'errors' => $errors,
            ]);

            return response()->json([
                'data' => [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'errors' => $errors,
                ],
                'status' => 200,
                'message' => "Import completed. {$successCount} users imported successfully" . ($failureCount > 0 ? ", {$failureCount} failed" : ""),
            ], 200);
        } catch (\Exception $e) {
            \Log::error('School users import failed', [
                'school_id' => $schoolId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk import organization users from Excel file
     * Admin only
     */
    public function importOrganizationUsers(Request $request)
    {
        // Try to get user from request (if middleware is applied)
        $currentUser = $request->user();

        // If no user from middleware, try to authenticate manually using bearer token
        if (!$currentUser) {
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Please provide a valid token',
                ], 401);
            }

            // Find the token and get the user
            $accessToken = PersonalAccessToken::findToken($token);
            
            if (!$accessToken) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Invalid token',
                ], 401);
            }

            $currentUser = $accessToken->tokenable;
            
            if (!$currentUser) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - User not found',
                ], 401);
            }
        }

        // Check if user is authenticated and is admin
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'data' => [],
                'status' => 403,
                'message' => 'Forbidden - Admin access required',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->input('organization_id');
        $file = $request->file('file');

        try {
            $import = new OrganizationUsersImport($organizationId);
            Excel::import($import, $file);

            $successCount = $import->getSuccessCount();
            $failureCount = $import->getFailureCount();
            $errors = $import->getErrors();

            return response()->json([
                'data' => [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'errors' => $errors,
                ],
                'status' => 200,
                'message' => "Import completed. {$successCount} users imported successfully" . ($failureCount > 0 ? ", {$failureCount} failed" : ""),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to find age group ID based on user's age
     */
    private function getAgeGroupIdByAge($age)
    {
        $ageGroup = AgeGroup::where('from', '<=', $age)
            ->where('to', '>=', $age)
            ->where('is_active', true)
            ->first();

        return $ageGroup ? $ageGroup->id : null;
    }
}


