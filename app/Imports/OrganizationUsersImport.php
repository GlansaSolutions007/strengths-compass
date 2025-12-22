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

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
        $this->organization = Organization::find($organizationId);
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
        // Required fields
        $firstName = trim($row['first_name'] ?? '');
        $lastName = trim($row['last_name'] ?? '');
        
        if (empty($firstName) || empty($lastName)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'First name and last name are required'
            ];
            return null;
        }

        // Employee ID is required for organization users
        $employeeId = trim($row['employee_id'] ?? '');
        
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

        // Password is required from Excel
        $password = trim($row['password'] ?? '');
        if (empty($password)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Password is required'
            ];
            return null;
        }

        // Email is optional for organization users (use username as email)
        $email = $username;

        // Age is required for organization users
        $age = isset($row['age']) ? (int) $row['age'] : null;
        if (!$age || $age < 1 || $age > 150) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Valid age (1-150) is required'
            ];
            return null;
        }

        // Get age group based on age
        $ageGroupId = $this->getAgeGroupIdByAge($age);

        // Gender (optional, with default)
        $gender = isset($row['gender']) ? strtolower(trim($row['gender'])) : 'prefer_not_to_say';
        $validGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
        if (!in_array($gender, $validGenders)) {
            $gender = 'prefer_not_to_say';
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

        $this->successCount++;

        return new User($userData);
    }

    /**
     * Validation rules for each row
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'employee_id' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'age' => 'required|integer|min:1|max:150',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'contact_number' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',
            'educational_qualification' => 'nullable|string|max:255',
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
