<?php

namespace App\Imports;

use App\Models\QuestionsModel as Question;
use App\Models\Cluster;
use App\Models\Construct;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

/**
 * Test Questions Import Class for Excel Bulk Upload
 * Used when creating/updating tests with questions
 * Note: Questions are saved immediately (not batched) to get IDs for test attachment
 */
class TestQuestionsImport implements ToModel, WithHeadingRow, SkipsOnFailure
{
    use SkipsFailures;

    protected $testId;
    protected $ageGroupId;
    protected $testSource;
    protected $errors = [];
    protected $successCount = 0;
    protected $failureCount = 0;
    protected $createdQuestions = [];

    public function __construct($testId = null, $ageGroupId = null, $testSource = null)
    {
        $this->testId = $testId;
        $this->ageGroupId = $ageGroupId;
        $this->testSource = $testSource ?? 'SC Pro'; // Default to SC Pro
    }

    /**
     * By default, Laravel Excel "slugifies" heading row values.
     * We want to use the headings exactly as they appear in the Excel file.
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
        // Skip empty rows
        if (empty(array_filter($row))) {
            return null;
        }

        // Debug: Log first few rows to help identify column name issues
        static $rowCount = 0;
        $rowCount++;
        if ($rowCount <= 3) {
            \Log::info('TestQuestionsImport - Processing row', [
                'row_number' => $rowCount,
                'keys' => array_keys($row),
                'row_data' => $row
            ]);
        }

        // Normalize column names (handle case-insensitive matching and variations)
        // Try multiple variations of column names
        $clusterName = '';
        $constructName = '';
        $questionText = '';
        $category = '';

        // Find cluster column (case-insensitive)
        foreach ($row as $key => $value) {
            $normalizedKey = strtolower(trim($key));
            if ($normalizedKey === 'cluster') {
                $clusterName = trim($value ?? '');
            } elseif ($normalizedKey === 'construct') {
                $constructName = trim($value ?? '');
            } elseif ($normalizedKey === 'question' || $normalizedKey === 'questions') {
                $questionText = trim($value ?? '');
            } elseif ($normalizedKey === 'category' || $normalizedKey === 'categories') {
                $category = strtoupper(trim($value ?? ''));
            }
        }

        // Fallback to direct access if not found
        if (empty($clusterName)) {
            $clusterName = trim($row['Cluster'] ?? $row['cluster'] ?? '');
        }
        if (empty($constructName)) {
            $constructName = trim($row['Construct'] ?? $row['construct'] ?? '');
        }
        if (empty($questionText)) {
            $questionText = trim($row['Question'] ?? $row['question'] ?? '');
        }
        if (empty($category)) {
            $category = strtoupper(trim($row['Category'] ?? $row['category'] ?? ''));
        }

        // Validate required fields
        if (empty($clusterName)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Cluster name is required'
            ];
            return null;
        }

        if (empty($constructName)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Construct name is required'
            ];
            return null;
        }

        if (empty($questionText)) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Question text is required'
            ];
            return null;
        }

        // Validate category
        if (!in_array($category, ['P', 'R', 'SDB'])) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Invalid category: {$category}. Must be P, R, or SDB"
            ];
            return null;
        }

        // Find cluster by name (case-insensitive, flexible matching)
        $cluster = $this->findClusterByName($clusterName);
        if (!$cluster) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Cluster '{$clusterName}' not found"
            ];
            return null;
        }

        // Validate age group if provided
        if ($this->ageGroupId && $cluster->age_group_id != $this->ageGroupId) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Cluster '{$clusterName}' does not belong to the specified age group"
            ];
            return null;
        }

        // Find construct by name within the cluster (case-insensitive, flexible matching)
        $construct = $this->findConstructByName($constructName, $cluster->id);
        if (!$construct) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Construct '{$constructName}' not found in cluster '{$clusterName}'"
            ];
            return null;
        }

        // Validate construct age group if provided
        if ($this->ageGroupId && $construct->age_group_id && $construct->age_group_id != $this->ageGroupId) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Construct '{$constructName}' does not belong to the specified age group"
            ];
            return null;
        }

        // Create and save question immediately
        // Source can come from Excel row, or default to test source
        $source = $this->testSource;
        if (isset($row['Source']) || isset($row['source'])) {
            $sourceFromRow = strtoupper(trim($row['Source'] ?? $row['source'] ?? ''));
            if (in_array($sourceFromRow, ['SC PRO', 'CERC'])) {
                $source = $sourceFromRow === 'SC PRO' ? 'SC Pro' : 'CERC';
            }
        }
        
        $question = Question::create([
            'construct_id' => $construct->id,
            'question_text' => $questionText,
            'age_group_id' => $this->ageGroupId ?? $cluster->age_group_id ?? $construct->age_group_id,
            'category' => $category,
            'order_no' => 0,
            'is_active' => true,
            'source' => $source,
        ]);

        $this->successCount++;
        $this->createdQuestions[] = [
            'question_id' => $question->id,
            'cluster_id' => $cluster->id,
        ];

        return $question;
    }

    /**
     * Find cluster by name (flexible matching)
     */
    private function findClusterByName(string $name): ?Cluster
    {
        $normalizedInput = $this->normalizeNameForComparison($name);
        
        $query = Cluster::where('is_active', true)
            ->where('is_deleted', false);
        
        if ($this->ageGroupId) {
            $query->where('age_group_id', $this->ageGroupId);
        }
        
        $clusters = $query->get();
        
        return $clusters->first(function ($cluster) use ($normalizedInput) {
            return $this->normalizeNameForComparison($cluster->name) === $normalizedInput;
        });
    }

    /**
     * Find construct by name within a cluster (flexible matching)
     */
    private function findConstructByName(string $name, int $clusterId): ?Construct
    {
        $normalizedInput = $this->normalizeNameForComparison($name);
        
        $query = Construct::where('cluster_id', $clusterId)
            ->where('is_active', true)
            ->where('is_deleted', false);
        
        if ($this->ageGroupId) {
            $query->where('age_group_id', $this->ageGroupId);
        }
        
        $constructs = $query->get();
        
        return $constructs->first(function ($construct) use ($normalizedInput) {
            return $this->normalizeNameForComparison($construct->name) === $normalizedInput;
        });
    }

    /**
     * Normalize name for flexible comparison
     */
    private function normalizeNameForComparison(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);
        return $normalized;
    }

    /**
     * Validation rules
     * Made flexible to handle case variations
     */
    public function rules(): array
    {
        return [
            // Allow any case variation of column names
            'Cluster' => 'sometimes|nullable|string',
            'cluster' => 'sometimes|nullable|string',
            'Construct' => 'sometimes|nullable|string',
            'construct' => 'sometimes|nullable|string',
            'Question' => 'sometimes|nullable|string',
            'question' => 'sometimes|nullable|string',
            'Category' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string',
        ];
    }

    /**
     * Override onFailure to log validation failures
     */
    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures)
    {
        // Store failures using the trait's method
        $this->failures = array_merge($this->failures ?? [], $failures);
        
        // Log and track in our custom errors array
        foreach ($failures as $failure) {
            $this->failureCount++;
            $errorMessage = $failure->attribute() . ': ' . implode(', ', $failure->errors());
            
            \Log::warning('Test questions import validation failed', [
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

    /**
     * Get import statistics
     */
    public function getStats()
    {
        return [
            'success' => $this->successCount,
            'failures' => $this->failureCount,
            'errors' => $this->errors,
            'created_questions' => $this->createdQuestions,
        ];
    }
}

