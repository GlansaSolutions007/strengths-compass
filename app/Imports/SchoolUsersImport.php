<?php

namespace App\Imports;

use App\Models\User;
use App\Models\AgeGroup;
use App\Models\School;
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

class SchoolUsersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use SkipsFailures;

    protected $schoolId;
    protected $school;
    protected $errors = [];
    protected $successCount = 0;
    protected $failureCount = 0;

    public function __construct($schoolId)
    {
        $this->schoolId = $schoolId;
        $this->school = School::find($schoolId);
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

        // Class and Registration No are required for school users
        $class = trim($row['class'] ?? '');
        $registrationNo = trim($row['registration_no'] ?? '');
        
        if (empty($class) || empty($registrationNo)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Class and Registration No are required for school users'
            ];
            return null;
        }

        // Check if school exists and has shortcode
        if (!$this->school) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'School not found'
            ];
            return null;
        }

        if (empty($this->school->shortcode)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'School shortcode is not set. Please set shortcode for the school first.'
            ];
            return null;
        }

        // Generate username: shortcode + class + registration_no
        $username = strtolower($this->school->shortcode . $class . $registrationNo);
        
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

        // Email is optional for school users (use username as email)
        $email = $username;

        // Age is required for school users
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
            'user_type' => 'school',
            'school_id' => $this->schoolId,
            'gender' => $gender,
            'age' => $age,
            'age_group_id' => $ageGroupId,
            'class' => $class,
            'registration_no' => $registrationNo,
        ];

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
            'class' => 'required|string|max:255',
            'registration_no' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'age' => 'required|integer|min:1|max:150',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'contact_number' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
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
