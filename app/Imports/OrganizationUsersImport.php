<?php

namespace App\Imports;

use App\Models\User;
use App\Models\AgeGroup;
use App\Models\Organization;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrganizationUsersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use SkipsFailures;

    protected $organizationId;
    protected $organization;
    protected $errors = [];
    protected $successCount = 0;
    protected $failureCount = 0;

    /**
     * Override onFailure to log validation failures
     * Note: SkipsFailures trait already implements onFailure, so we override it
     */
    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures)
    {
        // Store failures using the trait's method (access protected property)
        $this->failures = array_merge($this->failures ?? [], $failures);
        
        // Log and track in our custom errors array
        foreach ($failures as $failure) {
            $this->failureCount++;
            $errorMessage = $failure->attribute() . ': ' . implode(', ', $failure->errors());
            
            \Log::warning('Organization user import validation failed', [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ]);
            
            $this->errors[] = [
                'row' => $failure->row(),
                'error' => $errorMessage,
                'values' => $failure->values(),
            ];
        }
    }

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
        $this->organization = Organization::find($organizationId);
        
        \Log::info('OrganizationUsersImport initialized', [
            'organization_id' => $organizationId,
            'organization_found' => $this->organization ? true : false,
            'organization_shortcode' => $this->organization ? $this->organization->shortcode : null,
        ]);
    }

    /**
     * Disable automatic heading formatter to use exact column names
     */
    public static function bootHeadingFormatter(): void
    {
        HeadingRowFormatter::default('none');
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Debug: Log the row being processed - this means validation passed!
        \Log::info('Processing organization user row (validation passed)', [
            'row' => $row,
            'row_keys' => array_keys($row),
            'organization_id' => $this->organizationId
        ]);

        // Required fields - check with case-insensitive keys
        $firstName = '';
        $lastName = '';
        
        // Try to find first_name and last_name with case-insensitive matching
        foreach ($row as $key => $value) {
            $lowerKey = strtolower(trim($key));
            if ($lowerKey === 'first_name' || $lowerKey === 'firstname') {
                $firstName = trim((string) ($value ?? ''));
            }
            if ($lowerKey === 'last_name' || $lowerKey === 'lastname') {
                $lastName = trim((string) ($value ?? ''));
            }
        }
        
        if (empty($firstName) || empty($lastName)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'First name and last name are required. Found keys: ' . implode(', ', array_keys($row))
            ];
            return null;
        }

        // Employee ID is required for organization users - case-insensitive matching
        $employeeId = '';
        foreach ($row as $key => $value) {
            $lowerKey = strtolower(trim($key));
            if ($lowerKey === 'employee_id' || $lowerKey === 'employeeid') {
                // Convert to string (Excel may read as number)
                $employeeId = trim((string) ($value ?? ''));
                break;
            }
        }
        
        if (empty($employeeId)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Employee ID is required for organization users'
            ];
            return null;
        }

        // Check if organization exists and has shortcode
        if (!$this->organization) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Organization not found'
            ];
            return null;
        }

        if (empty($this->organization->shortcode)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Organization shortcode is not set. Please set shortcode for the organization first.'
            ];
            return null;
        }

        // Generate username: shortcode + employee_id
        $username = strtolower($this->organization->shortcode . $employeeId);
        
        // Check if username already exists
        if (User::where('email', $username)->exists()) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Username '{$username}' already exists"
            ];
            return null;
        }

        // Password is required from Excel - case-insensitive matching
        $password = '';
        foreach ($row as $key => $value) {
            $lowerKey = strtolower(trim($key));
            if ($lowerKey === 'password') {
                // Convert to string (Excel may read as number)
                $password = trim((string) ($value ?? ''));
                break;
            }
        }
        
        if (empty($password)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Password is required'
            ];
            return null;
        }
        
        // Validate password length after conversion
        if (strlen($password) < 6) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Password must be at least 6 characters long'
            ];
            return null;
        }

        // Email is optional for organization users (use username as email)
        $email = $username;

        // Age is required for organization users - case-insensitive matching
        $age = null;
        foreach ($row as $key => $value) {
            $lowerKey = strtolower(trim($key));
            if ($lowerKey === 'age') {
                $age = isset($value) ? (int) $value : null;
                break;
            }
        }
        
        if (!$age || $age < 1 || $age > 150) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Valid age (1-150) is required. Found: ' . ($age ?? 'null')
            ];
            return null;
        }

        // Get age group based on age
        $ageGroupId = $this->getAgeGroupIdByAge($age);

        // Gender (optional, with default) - handle case-insensitive matching
        $gender = 'prefer_not_to_say'; // default
        $genderValue = '';
        foreach ($row as $key => $value) {
            $lowerKey = strtolower(trim($key));
            if ($lowerKey === 'gender') {
                $genderValue = trim((string) ($value ?? ''));
                break;
            }
        }
        
        if (!empty($genderValue)) {
            $genderInput = strtolower($genderValue);
            // Also check for common variations
            $genderMap = [
                'male' => 'male',
                'female' => 'female',
                'm' => 'male',
                'f' => 'female',
                'other' => 'other',
                'prefer_not_to_say' => 'prefer_not_to_say',
            ];
            $gender = $genderMap[$genderInput] ?? 'prefer_not_to_say';
        }

        // Hash password
        $hashedPassword = Hash::make($password);

        // Build user data
        $userData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName . ' ' . $lastName),
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'user',
            'user_type' => 'organization',
            'organization_id' => $this->organizationId,
            'gender' => $gender,
            'age' => $age,
            'employee_id' => $employeeId,
        ];

        // Add age_group_id if found
        if ($ageGroupId) {
            $userData['age_group_id'] = $ageGroupId;
        }

        // Optional fields
        if (isset($row['contact_number']) && !empty($row['contact_number'])) {
            $userData['contact_number'] = trim($row['contact_number']);
        }
        if (isset($row['whatsapp_number']) && !empty($row['whatsapp_number'])) {
            $userData['whatsapp_number'] = trim($row['whatsapp_number']);
        }
        if (isset($row['city']) && !empty($row['city'])) {
            $userData['city'] = trim($row['city']);
        }
        if (isset($row['state']) && !empty($row['state'])) {
            $userData['state'] = trim($row['state']);
        }
        if (isset($row['country']) && !empty($row['country'])) {
            $userData['country'] = trim($row['country']);
        }
        if (isset($row['profession']) && !empty($row['profession'])) {
            $userData['profession'] = trim($row['profession']);
        }
        if (isset($row['educational_qualification']) && !empty($row['educational_qualification'])) {
            $userData['educational_qualification'] = trim($row['educational_qualification']);
        }

        try {
            // Create user - ToModel will save it automatically
            $user = new User($userData);
            
            // Increment success count only if we get here without errors
            $this->successCount++;
            
            return $user;
        } catch (\Exception $e) {
            // If user creation fails, increment failure count
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Failed to create user: ' . $e->getMessage()
            ];
            return null;
        }
    }

    /**
     * Validation rules for each row
     */
    /**
     * Validation rules for each row
     * Note: These rules accept both string and numeric values (Excel may read numbers as numeric)
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'employee_id' => 'required|max:255', // Accept any type, we'll convert to string
            'password' => 'required|min:6', // Accept any type, we'll convert to string
            'age' => 'required|integer|min:1|max:150',
            'gender' => 'nullable|max:255', // Made more flexible - we handle conversion in model()
            'contact_number' => 'nullable|max:20',
            'whatsapp_number' => 'nullable|max:20',
            'city' => 'nullable|max:255',
            'state' => 'nullable|max:255',
            'country' => 'nullable|max:255',
            'profession' => 'nullable|max:255',
            'educational_qualification' => 'nullable|max:255',
        ];
    }

    /**
     * Get age group ID based on age
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
     * Batch size for inserts
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Chunk size for reading
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Get success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get failure count
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
