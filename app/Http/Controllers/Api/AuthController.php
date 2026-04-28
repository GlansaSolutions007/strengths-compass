<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Mail\ForgotPasswordMail;
use App\Models\User;
use App\Models\AgeGroup;
use App\Models\Role;
use App\Models\TeacherData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register a new user
     * Different validation rules for admin vs regular user
     */
    public function register(Request $request)
    {
        // \Log::info("REGISTER METHOD HIT");
        $requestedRole = strtolower((string) $request->input('role', 'user'));
        $role = in_array($requestedRole, ['admin', 'teacher'], true) ? $requestedRole : 'user';


        // Base validation rules (common for all)
        $rules = [
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|string|max:255',
        ];


        // Admin-specific validation
        if ($role === 'admin') {
            $rules['name'] = 'required|string|max:255';
        }
        // Regular user validation
        else {
            $rules['first_name'] = 'required|string|max:255';
            $rules['last_name'] = 'required|string|max:255';
            $rules['whatsapp_number'] = 'required|string|max:20';
            $rules['contact_number'] = 'required|string|max:20';
            $rules['city'] = 'nullable|string|max:255';
            $rules['state'] = 'nullable|string|max:255';
            $rules['country'] = 'required|string|max:255';
            $rules['profession'] = 'required|string|max:255';
            $rules['gender'] = 'required|in:male,female,other,prefer_not_to_say';
            $rules['age'] = 'required|integer|min:1|max:150';
            $rules['educational_qualification'] = 'required|string|max:255';
            // $rules['role'] = 'required|string|max:255'; // Ensure role is valid for regular users
        }

        // Teacher-specific validation
        if ($role === 'teacher') {
            $rules['tch_teaching_subject'] = 'required|string|max:255';
            $rules['tch_teaching_level'] = 'required|string|max:255';
            $rules['tch_years_of_experience'] = 'required|integer|min:0';
            $rules['tch_current_role'] = 'required|string|max:255';
            $rules['tch_school_context'] = 'required|string|max:255';
            $rules['tch_school_name'] = 'required|string|max:255';
            $rules['tch_school_city'] = 'required|string|max:255';
            $rules['tch_school_state'] = 'required|string|max:255';
            $rules['tch_consent'] = 'required|boolean';
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

        // Prepare user data based on role
        $userData = [
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
        ];


        if ($role === 'admin') {
            // Admin fields
            $userData['name'] = $request->name;
        } else {

            // Regular user fields
            $userData['first_name'] = $request->first_name;
            $userData['last_name'] = $request->last_name;
            $userData['whatsapp_number'] = $request->whatsapp_number;
            $userData['contact_number'] = $request->contact_number;
            $userData['city'] = $request->city;
            $userData['state'] = $request->state;
            $userData['country'] = $request->country;
            $userData['profession'] = $request->profession;
            $userData['gender'] = $request->gender;
            $userData['age'] = $request->age;
            $userData['educational_qualification'] = $request->educational_qualification;


            if ($request->age) {
                $ageGroupId = $this->getAgeGroupIdByAgeAndProfession($request->age, $request->profession);
                if ($ageGroupId) {
                    $userData['age_group_id'] = $ageGroupId;
                }
            }

            // Set name as combination of first_name and last_name for backward compatibility
            $userData['name'] = trim($request->first_name . ' ' . $request->last_name);
        }
        // print_r($userData);
        //     // exit();
        $user = User::create($userData);

        // Insert teacher-specific data after user creation
        if ($role === 'teacher') {
            TeacherData::create([
                'tch_user_id'             => $user->id,
                'tch_teaching_subject'    => $request->tch_teaching_subject,
                'tch_teaching_level'      => $request->tch_teaching_level,
                'tch_years_of_experience' => $request->tch_years_of_experience,
                'tch_current_role'        => $request->tch_current_role,
                'tch_school_context'      => $request->tch_school_context,
                'tch_school_name'         => $request->tch_school_name,
                'tch_school_city'         => $request->tch_school_city,
                'tch_school_state'        => $request->tch_school_state,
                'tch_consent'             => $request->tch_consent,
            ]);
        }

        // Refresh user to ensure all attributes are loaded
        $user->refresh();

        // Send welcome email immediately after registration
        // Use a separate try-catch to ensure registration doesn't fail if email fails
        try {
            // Force flush logs to ensure we see the attempt
            \Log::info('=== REGISTRATION: Starting email send process ===', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name ?? 'N/A',
            ]);

            // Validate email exists
            if (empty($user->email)) {
                \Log::warning('Cannot send welcome email: user email is empty', ['user_id' => $user->id]);
            } else {
                // Test if we can render the view first
                try {
                    $view = view('emails.welcome', ['user' => $user]);
                    $html = $view->render();
                    \Log::info('Email view rendered successfully', ['size' => strlen($html)]);
                } catch (\Exception $viewError) {
                    \Log::error('Failed to render welcome email view', [
                        'error' => $viewError->getMessage(),
                        'file' => $viewError->getFile(),
                        'line' => $viewError->getLine(),
                    ]);
                    throw $viewError;
                }

                // Send email synchronously (not queued)
                \Log::info('Attempting to send welcome email via Mail::send()', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                // Try sending with WelcomeMail mailable first
                try {
                    Mail::to($user->email)->send(new WelcomeMail($user));
                    \Log::info('=== REGISTRATION: Welcome email sent successfully via WelcomeMail ===', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);
                } catch (\Exception $mailError) {
                    // If WelcomeMail fails, try with Mail::raw as fallback (like test email)
                    \Log::warning('WelcomeMail failed, trying Mail::raw fallback', [
                        'error' => $mailError->getMessage(),
                    ]);

                    $userName = $user->name ?? $user->first_name ?? 'there';
                    Mail::raw("Hello {$userName},\n\nWelcome to Strengths Compass! Your account has been successfully created.\n\nBest regards,\nThe Strengths Compass Team", function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject('Welcome to Strengths Compass!');
                    });

                    \Log::info('=== REGISTRATION: Welcome email sent successfully via Mail::raw fallback ===', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Log the error with full details but don't fail registration
            \Log::error('=== REGISTRATION: Failed to send welcome email ===', [
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Set age group in session based on user role
        $ageGroupId = null;
        $ageGroup = null;

        if (($role === 'user' || $role === 'teacher') && $user->age) {
            $ageGroupId = $user->age_group_id ?: $this->getAgeGroupIdByAgeAndProfession($user->age, $user->profession);
            if ($ageGroupId) {
                session(['selected_age_group_id' => $ageGroupId]);
                session()->save(); // Ensure session is saved
                $ageGroup = AgeGroup::find($ageGroupId);
            }
        }
        // For admin, age group will be set manually via the set-age-group endpoint

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'age_group_id' => $ageGroupId, // Include in response for frontend
                'age_group' => $ageGroup, // Include age group details
            ],
            'status' => 201,
            'message' => ucfirst($role) . ' registered successfully',
        ], 201);
    }

    /**
     * Login user and create token
     * Accepts either email or username (for school/organization users, email field contains username)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string', // Removed 'email' validation to accept username format
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check both email and username fields (email field stores username for school/org users)
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'data' => [],
                'status' => 401,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Set age group in session based on user role
        $ageGroupId = null;
        $ageGroup = null;

        if (($user->role === 'user' || $user->role === 'teacher') && $user->age) {
            $ageGroupId = $user->age_group_id ?: $this->getAgeGroupIdByAgeAndProfession($user->age, $user->profession);
            if ($ageGroupId) {
                session(['selected_age_group_id' => $ageGroupId]);
                session()->save(); // Ensure session is saved
                $ageGroup = AgeGroup::find($ageGroupId);
            }
        } else if ($user->role === 'admin') {
            // For admin, check if they have a previously selected age group in session
            $ageGroupId = session('selected_age_group_id');
            if ($ageGroupId) {
                $ageGroup = AgeGroup::find($ageGroupId);
                // If age group was found in session but doesn't exist anymore, clear it
                if (!$ageGroup) {
                    session()->forget('selected_age_group_id');
                    session()->save();
                    $ageGroupId = null;
                }
            }
        }

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'age_group_id' => $ageGroupId, // Include in response for frontend
                'age_group' => $ageGroup, // Include age group details
            ],
            'status' => 200,
            'message' => 'Login successful',
        ], 200);
    }

    /**
     * Logout user (Revoke the token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        // Clear age group from session on logout
        session()->forget('selected_age_group_id');

        return response()->json([
            'data' => [],
            'status' => 200,
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Change password for authenticated user
     * Users can change their own password by providing current password
     */
    public function changePassword(Request $request)
    {
        // Try to get user from request (if middleware is applied)
        $user = $request->user();

        // If no user from middleware, try to authenticate manually using bearer token
        if (!$user) {
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

            $user = $accessToken->tokenable;

            if (!$user) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - User not found',
                ], 401);
            }
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
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

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'data' => [],
                'status' => 400,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        // Check if new password is different from current password
        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'data' => [],
                'status' => 400,
                'message' => 'New password must be different from current password',
            ], 400);
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
     * Forgot password - Send temporary password and reset link via email
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Don't reveal if email exists or not for security
        if (!$user) {
            return response()->json([
                'data' => [],
                'status' => 200,
                'message' => 'If the email exists, a password reset link has been sent.',
            ], 200);
        }

        // Generate a random temporary password (8-12 characters, alphanumeric)
        $temporaryPassword = Str::random(10);

        // Generate reset token
        $resetToken = Str::random(64);

        // Delete any existing reset tokens for this email
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Store the reset token and hashed temporary password
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($resetToken),
            'temporary_password' => Hash::make($temporaryPassword),
            'created_at' => now(),
        ]);

        // Generate reset URL (you may need to adjust this based on your frontend URL)
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $resetUrl = $frontendUrl . 'reset-password?token=' . $resetToken;

        // Send email
        try {
            Mail::to($user->email)->send(new ForgotPasswordMail($user, $temporaryPassword, $resetUrl));
        } catch (\Exception $e) {
            \Log::error('Failed to send forgot password email', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Failed to send email. Please try again later.',
            ], 500);
        }

        return response()->json([
            'data' => [],
            'status' => 200,
            'message' => 'Password reset link has been sent to your email.',
        ], 200);
    }

    /**
     * Reset password using temporary password and token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'temporary_password' => 'required|string',
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

        // Find the password reset record by checking all non-expired tokens
        // We need to check each token hash to find the matching one since tokens are hashed
        $passwordResets = DB::table('password_reset_tokens')
            ->where('created_at', '>=', now()->subMinutes(60))
            ->get();

        $passwordReset = null;

        foreach ($passwordResets as $reset) {
            if (Hash::check($request->token, $reset->token)) {
                $passwordReset = $reset;
                break;
            }
        }

        if (!$passwordReset) {
            return response()->json([
                'data' => [],
                'status' => 400,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        // Check if token is expired (60 minutes)
        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $passwordReset->email)->delete();
            return response()->json([
                'data' => [],
                'status' => 400,
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        // Verify the temporary password
        if (!Hash::check($request->temporary_password, $passwordReset->temporary_password)) {
            return response()->json([
                'data' => [],
                'status' => 400,
                'message' => 'Invalid temporary password.',
            ], 400);
        }

        // Find the user using the email from the token record
        $user = User::where('email', $passwordReset->email)->first();

        if (!$user) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'User not found.',
            ], 404);
        }

        // Update the password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'data' => [
                'user' => $user,
            ],
            'status' => 200,
            'message' => 'Password has been reset successfully.',
        ], 200);
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

    /**
     * Resolve age group by matching the user's profession to roles.name, then
     * matching the age range for that role. Falls back to role 1.
     */
    private function getAgeGroupIdByAgeAndProfession($age, ?string $profession = null)
    {
        $roleId = AgeGroup::DEFAULT_ROLE_ID;
        $profession = trim((string) $profession);

        if ($profession !== '') {
            $normalizedProfession = strtolower($profession);
            $roleNames = [$normalizedProfession];
            if (str_ends_with($normalizedProfession, 's')) {
                $roleNames[] = substr($normalizedProfession, 0, -1);
            }

            $role = Role::whereIn(DB::raw('LOWER(name)'), array_unique($roleNames))->first();
            if ($role) {
                $roleId = $role->id;
            }
        }

        $ageGroup = AgeGroup::where('role', $roleId)
            ->where('from', '<=', $age)
            ->where('to', '>=', $age)
            ->where('is_active', true)
            ->first();

        if (!$ageGroup && $roleId !== AgeGroup::DEFAULT_ROLE_ID) {
            $ageGroup = AgeGroup::where('role', AgeGroup::DEFAULT_ROLE_ID)
                ->where('from', '<=', $age)
                ->where('to', '>=', $age)
                ->where('is_active', true)
                ->first();
        }

        return $ageGroup ? $ageGroup->id : null;
    }

    /**
     * Set age group for admin (or override for user)
     * Admin can select any age group, users can only see their own age group
     */
    public function setAgeGroup(Request $request)
    {
        $user = $request->user();

        // If no user from middleware, try to authenticate manually using bearer token
        if (!$user) {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Please provide a valid token',
                ], 401);
            }

            $accessToken = PersonalAccessToken::findToken($token);

            if (!$accessToken) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Invalid token',
                ], 401);
            }

            $user = $accessToken->tokenable;

            if (!$user) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - User not found',
                ], 401);
            }
        }

        $validator = Validator::make($request->all(), [
            'age_group_id' => 'required|exists:age_groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ageGroupId = $request->input('age_group_id');

        // For regular users, verify they can only select their own age group
        if ($user->role === 'user') {
            if (!$user->age) {
                return response()->json([
                    'data' => [],
                    'status' => 403,
                    'message' => 'User age is not set. Cannot determine age group.',
                ], 403);
            }

            $userAgeGroupId = $user->age_group_id ?: $this->getAgeGroupIdByAgeAndProfession($user->age, $user->profession);

            if ($ageGroupId != $userAgeGroupId) {
                return response()->json([
                    'data' => [],
                    'status' => 403,
                    'message' => 'You can only access data for your own age group.',
                ], 403);
            }
        }

        // Verify age group exists and is active
        $ageGroup = AgeGroup::find($ageGroupId);
        if (!$ageGroup) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Age group not found',
            ], 404);
        }

        // Store in session and ensure it's saved
        session(['selected_age_group_id' => $ageGroupId]);
        session()->save(); // Explicitly save session to ensure persistence

        return response()->json([
            'data' => [
                'age_group' => $ageGroup,
                'age_group_id' => $ageGroupId,
            ],
            'status' => 200,
            'message' => 'Age group set successfully and stored in session',
        ], 200);
    }

    /**
     * Get current selected age group
     */
    public function getCurrentAgeGroup(Request $request)
    {
        $user = $request->user();

        // If no user from middleware, try to authenticate manually using bearer token
        if (!$user) {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Please provide a valid token',
                ], 401);
            }

            $accessToken = PersonalAccessToken::findToken($token);

            if (!$accessToken) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - Invalid token',
                ], 401);
            }

            $user = $accessToken->tokenable;

            if (!$user) {
                return response()->json([
                    'data' => [],
                    'status' => 401,
                    'message' => 'Unauthorized - User not found',
                ], 401);
            }
        }

        $ageGroupId = session('selected_age_group_id');

        if (!$ageGroupId) {
            // For users, try to determine from their age
            if (($user->role === 'user' || $user->role === 'teacher') && $user->age) {
                $ageGroupId = $user->age_group_id ?: $this->getAgeGroupIdByAgeAndProfession($user->age, $user->profession);
                if ($ageGroupId) {
                    session(['selected_age_group_id' => $ageGroupId]);
                }
            }
        }

        $ageGroup = null;
        if ($ageGroupId) {
            $ageGroup = AgeGroup::find($ageGroupId);
        }

        return response()->json([
            'data' => [
                'age_group' => $ageGroup,
                'age_group_id' => $ageGroupId,
            ],
            'status' => 200,
            'message' => 'Current age group retrieved successfully',
        ], 200);
    }
}
