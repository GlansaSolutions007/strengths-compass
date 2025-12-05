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
    protected $errors = [];
    protected $successCount = 0;
    protected $failureCount = 0;

    public function __construct($constructId = null)
    {
        $this->constructId = $constructId;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Construct ID is now optional - questions can be uploaded without assignment
        // If construct_id is provided (from parameter or Excel), validate it exists
        $constructId = $this->constructId ?? $row['construct_id'] ?? null;

        if ($constructId) {
            // Validate construct exists if provided
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

        // Handle age_group_id - can be provided directly or looked up by name
        $ageGroupId = null;
        if (isset($row['age_group_id'])) {
            $ageGroupId = $row['age_group_id'];
        } elseif (isset($row['age_group'])) {
            // Try to find age group by name
            $ageGroup = \App\Models\AgeGroup::where('name', $row['age_group'])->first();
            if ($ageGroup) {
                $ageGroupId = $ageGroup->id;
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

