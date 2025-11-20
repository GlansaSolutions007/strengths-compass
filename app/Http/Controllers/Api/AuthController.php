<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user
     * Different validation rules for admin vs regular user
     */
    public function register(Request $request)
    {
        \Log::info("REGISTER METHOD HIT");
        $role = $request->input('role', 'user');
        
        // Base validation rules (common for all)
        $rules = [
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:admin,user',
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
            $rules['city'] = 'required|string|max:255';
            $rules['state'] = 'required|string|max:255';
            $rules['country'] = 'required|string|max:255';
            $rules['profession'] = 'required|string|max:255';
            $rules['gender'] = 'required|in:male,female,other,prefer_not_to_say';
            $rules['age'] = 'required|integer|min:1|max:150';
            $rules['educational_qualification'] = 'required|string|max:255';
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
            
            // Set name as combination of first_name and last_name for backward compatibility
            $userData['name'] = trim($request->first_name . ' ' . $request->last_name);
        }

        $user = User::create($userData);
        
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

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'status' => 201,
            'message' => ucfirst($role) . ' registered successfully',
        ], 201);
    }

    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
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

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'data' => [],
                'status' => 401,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
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

        return response()->json([
            'data' => [],
            'status' => 200,
            'message' => 'Logged out successfully',
        ], 200);
    }
}



