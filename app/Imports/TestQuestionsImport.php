<?php

namespace App\Imports;

use App\Models\QuestionsModel as Question;
use App\Models\Cluster;
use App\Models\Construct;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

/**
 * Test Questions Import Class for Excel Bulk Upload
 *
 * Supports SC Pro (create first) and CERC (create after, can reuse questions/constructs).
 * - Constructs: reused by name; created if new (no duplication).
 * - Questions: reused by optional question_id, or by (question_text + construct_id); created if new (no duplication).
 * - Every row is attached to the test (test_question + test_cluster_construct) so reporting is correct.
 * - CERC: common questions with SC Pro are still attached to CERC test (with CERC cluster/construct);
 *   "don't show again" is enforced at test-taking time by filtering already-answered SC Pro questions.
 */
class TestQuestionsImport implements ToModel, WithHeadingRow, SkipsOnFailure
{
    use SkipsFailures;

    protected $testId;
    protected $ageGroupId;
    protected $testSource;
    protected $scProTestId;
    protected $errors = [];
    protected $successCount = 0;
    protected $failureCount = 0;
    protected $createdCount = 0;
    protected $reusedCount = 0;
    /** @var array<int, array{question_id: int, cluster_id: int}> All questions to attach to test (new + reused) */
    protected $createdQuestions = [];

    public function __construct($testId = null, $ageGroupId = null, $testSource = null, $scProTestId = null)
    {
        $this->testId = $testId;
        $this->ageGroupId = $ageGroupId;
        $this->testSource = $testSource ?? 'SC Pro';
        $this->scProTestId = $scProTestId;
    }

    /**
     * Normalize question text for comparison (trim, lowercase, single spaces)
     */
    private function normalizeQuestionText(string $text): string
    {
        $normalized = trim($text);
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
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

        $clusterName = '';
        $constructName = '';
        $questionText = '';
        $category = '';
        $questionIdFromRow = null;

        foreach ($row as $key => $value) {
            $normalizedKey = strtolower(trim(str_replace(' ', '_', $key)));
            $val = trim($value ?? '');
            if ($normalizedKey === 'cluster') {
                $clusterName = $val;
            } elseif ($normalizedKey === 'construct') {
                $constructName = $val;
            } elseif ($normalizedKey === 'question' || $normalizedKey === 'questions') {
                $questionText = $val;
            } elseif ($normalizedKey === 'category' || $normalizedKey === 'categories') {
                $category = strtoupper($val);
            } elseif ($normalizedKey === 'question_id' || $normalizedKey === 'questionid') {
                if (is_numeric($val)) {
                    $questionIdFromRow = (int) $val;
                }
            }
        }

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
        if ($questionIdFromRow === null && isset($row['Question ID'])) {
            $v = trim($row['Question ID'] ?? '');
            if (is_numeric($v)) {
                $questionIdFromRow = (int) $v;
            }
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

        // Construct: reuse by name or create if new (no duplication)
        $construct = $this->findOrCreateConstruct(
            $constructName,
            $cluster->age_group_id ?? $this->ageGroupId
        );
        if (!$construct) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Construct '{$constructName}' could not be found or created"
            ];
            return null;
        }

        if ($this->ageGroupId && $construct->age_group_id && $construct->age_group_id != $this->ageGroupId) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => "Construct '{$constructName}' does not belong to the specified age group"
            ];
            return null;
        }

        // Question: reuse by question_id (Excel), or by (question_text + construct_id), or create new (no duplication)
        $source = $this->testSource;
        if (isset($row['Source']) || isset($row['source'])) {
            $sourceFromRow = strtoupper(trim($row['Source'] ?? $row['source'] ?? ''));
            if (in_array($sourceFromRow, ['SC PRO', 'CERC'])) {
                $source = $sourceFromRow === 'SC PRO' ? 'SC Pro' : 'CERC';
            }
        }

        $question = $this->findOrCreateQuestion(
            $questionText,
            $construct->id,
            $category,
            $this->ageGroupId ?? $cluster->age_group_id ?? $construct->age_group_id,
            $source,
            $questionIdFromRow
        );
        if (!$question) {
            $this->failureCount++;
            $this->errors[] = [
                'row' => $row,
                'error' => 'Question could not be found or created'
            ];
            return null;
        }

        // Test-specific cluster–construct assignment (same construct can be in different clusters per test)
        if ($this->testId) {
            DB::table('test_cluster_construct')->insertOrIgnore([
                'test_id' => $this->testId,
                'cluster_id' => $cluster->id,
                'construct_id' => $construct->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->successCount++;
        $this->createdQuestions[] = [
            'question_id' => $question->id,
            'cluster_id' => $cluster->id,
        ];

        return $question;
    }

    /**
     * Find construct by name; if not found, create it (no duplication).
     */
    private function findOrCreateConstruct(string $name, $ageGroupId = null): ?Construct
    {
        $construct = $this->findConstructByName($name, $ageGroupId);
        if ($construct) {
            return $construct;
        }
        return Construct::create([
            'name' => $name,
            'cluster_id' => null,
            'age_group_id' => $ageGroupId ?? $this->ageGroupId,
            'is_active' => true,
            'is_deleted' => false,
            'source' => $this->testSource,
        ]);
    }

    /**
     * Find question by optional question_id, or by (question_text + construct_id); otherwise create (no duplication).
     */
    private function findOrCreateQuestion(
        string $questionText,
        int $constructId,
        string $category,
        $ageGroupId,
        string $source,
        ?int $questionIdFromRow = null
    ): ?Question {
        if ($questionIdFromRow !== null) {
            $existing = Question::find($questionIdFromRow);
            if ($existing) {
                $this->reusedCount++;
                return $existing;
            }
        }

        $normalized = $this->normalizeQuestionText($questionText);
        $existing = Question::where('construct_id', $constructId)
            ->where('is_active', true)
            ->get()
            ->first(function ($q) use ($normalized) {
                return $this->normalizeQuestionText($q->question_text) === $normalized;
            });
        if ($existing) {
            $this->reusedCount++;
            return $existing;
        }

        $this->createdCount++;
        return Question::create([
            'construct_id' => $constructId,
            'question_text' => $questionText,
            'age_group_id' => $ageGroupId,
            'category' => $category,
            'order_no' => 0,
            'is_active' => true,
            'source' => $source,
        ]);
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
     * Find construct by name (independent of cluster). Optionally filter by age_group_id.
     */
    private function findConstructByName(string $name, $ageGroupId = null): ?Construct
    {
        $normalizedInput = $this->normalizeNameForComparison($name);

        $query = Construct::where('is_active', true)
            ->where('is_deleted', false);

        $ageFilter = $ageGroupId ?? $this->ageGroupId;
        if ($ageFilter) {
            $query->where(function ($q) use ($ageFilter) {
                $q->where('age_group_id', $ageFilter)->orWhereNull('age_group_id');
            });
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
            'Cluster' => 'sometimes|nullable|string',
            'cluster' => 'sometimes|nullable|string',
            'Construct' => 'sometimes|nullable|string',
            'construct' => 'sometimes|nullable|string',
            'Question' => 'sometimes|nullable|string',
            'question' => 'sometimes|nullable|string',
            'Category' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string',
            'Question ID' => 'sometimes|nullable|integer',
            'question_id' => 'sometimes|nullable|integer',
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
            'created_count' => $this->createdCount,
            'reused_count' => $this->reusedCount,
            'errors' => $this->errors,
            'created_questions' => $this->createdQuestions,
        ];
    }
}

