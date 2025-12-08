<?php

namespace App\Imports;

use App\Models\QuestionsModel as Question;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Questions Import Class for Excel Bulk Upload
 */
class QuestionsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use SkipsFailures;

    /**
     * By default, Laravel Excel "slugifies" heading row values (e.g. "Question Text" -> "question-text").
     * We want to use the headings exactly as they appear in the Excel file (e.g. "question_text"),
     * so we disable the automatic formatter here.
     */
    public static function bootHeadingFormatter(): void
    {
        HeadingRowFormatter::default('none');
    }

    protected $constructId;
    protected $ageGroupId;
    protected $errors = [];
    protected $successCount = 0;
    protected $failureCount = 0;

    public function __construct($constructId = null, $ageGroupId = null)
    {
        $this->constructId = $constructId;
        $this->ageGroupId = $ageGroupId;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Construct ID is now optional - questions can be uploaded without assignment
        // Priority: constructor parameter > Excel construct_id > Excel construct_name
        $constructId = $this->constructId ?? null;

        // If not set from constructor, check Excel row
        if ($constructId === null) {
            if (isset($row['construct_id']) && !empty($row['construct_id'])) {
                // Validate construct_id exists
                $constructIdValue = is_numeric($row['construct_id']) ? (int)$row['construct_id'] : null;
                if ($constructIdValue) {
                    $construct = \App\Models\Construct::find($constructIdValue);
                    if ($construct) {
                        $constructId = $constructIdValue;
                    } else {
                        $this->failureCount++;
                        $this->errors[] = [
                            'row' => $row,
                            'error' => "Construct ID {$constructIdValue} does not exist"
                        ];
                        return null;
                    }
                }
            } elseif (isset($row['construct_name']) && !empty($row['construct_name'])) {
                // Try to find construct by name (case-insensitive)
                $constructName = trim($row['construct_name']);
                $construct = \App\Models\Construct::where('name', 'like', $constructName)->first();
                if ($construct) {
                    $constructId = $construct->id;
                } else {
                    $this->failureCount++;
                    $this->errors[] = [
                        'row' => $row,
                        'error' => "Construct '{$constructName}' not found"
                    ];
                    return null;
                }
            }
        } else {
            // Validate construct exists if provided from constructor
            if (!\App\Models\Construct::find($constructId)) {
                $this->failureCount++;
                $this->errors[] = [
                    'row' => $row,
                    'error' => "Construct ID {$constructId} does not exist"
                ];
                return null;
            }
        }

        // Map category values (handle case-insensitive)
        $category = strtoupper(trim($row['category'] ?? ''));
        if (!in_array($category, ['P', 'R', 'SDB'])) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Invalid category: {$category}. Must be P, R, or SDB"
            ];
            return null;
        }

        // Handle is_active - convert various formats to boolean
        $isActive = true;
        if (isset($row['is_active'])) {
            $isActiveValue = strtolower(trim($row['is_active']));
            $isActive = in_array($isActiveValue, ['1', 'true', 'yes', 'y', 'active']);
        }

        $this->successCount++;

        // Handle age_group_id - can be provided from constructor, Excel column, or looked up by name
        $ageGroupId = $this->ageGroupId ?? null;
        
        // If not set from constructor, check Excel row
        if ($ageGroupId === null) {
            if (isset($row['age_group_id']) && !empty($row['age_group_id'])) {
                // Validate age_group_id exists
                $ageGroupIdValue = is_numeric($row['age_group_id']) ? (int)$row['age_group_id'] : null;
                if ($ageGroupIdValue) {
                    $ageGroup = \App\Models\AgeGroup::find($ageGroupIdValue);
                    if ($ageGroup) {
                        $ageGroupId = $ageGroupIdValue;
                    } else {
                        $this->failureCount++;
                        $this->errors[] = [
                            'row' => $row,
                            'error' => "Age Group ID {$ageGroupIdValue} does not exist"
                        ];
                        return null;
                    }
                }
            } elseif (isset($row['age_group']) && !empty($row['age_group'])) {
                // Try to find age group by name (case-insensitive)
                $ageGroupName = trim($row['age_group']);
                $ageGroup = \App\Models\AgeGroup::where('name', 'like', $ageGroupName)->first();
                if ($ageGroup) {
                    $ageGroupId = $ageGroup->id;
                } else {
                    $this->failureCount++;
                    $this->errors[] = [
                        'row' => $row,
                        'error' => "Age Group '{$ageGroupName}' not found"
                    ];
                    return null;
                }
            }
        }

        return new Question([
            'construct_id' => $constructId,
            'question_text' => $row['question_text'] ?? $row['question'] ?? '',
            'age_group_id' => $ageGroupId,
            'category' => $category,
            'order_no' => $row['order_no'] ?? $row['order'] ?? 0,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            // Only question_text (or question) and category are required
            'question_text' => 'required_without:question',
            'question' => 'sometimes|required_without:question_text', // Alternative column name
            'category' => 'required|in:P,R,SDB',

            // order_no / order are optional, but must be integers if provided
            'order_no' => 'sometimes|nullable|integer',
            'order' => 'sometimes|nullable|integer', // Alternative column name

            // construct_id and construct_name are optional (will be validated in model() method)
            'construct_id' => 'sometimes|nullable|integer|exists:constructs,id',
            'construct_name' => 'sometimes|nullable|string', // Will be validated in model() method

            // age_group_id and age_group are optional
            'age_group_id' => 'sometimes|nullable|integer|exists:age_groups,id',
            'age_group' => 'sometimes|nullable|string', // Will be validated in model() method
        ];
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
     * Get import statistics
     */
    public function getStats()
    {
        return [
            'success' => $this->successCount,
            'failures' => $this->failureCount,
            'errors' => $this->errors,
        ];
    }
}

