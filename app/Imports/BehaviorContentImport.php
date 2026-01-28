<?php

namespace App\Imports;

use App\Models\Cluster;
use App\Models\Construct;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Collection;

/**
 * Behavior Content Import Class for Excel Bulk Update
 * Updates high, medium, and low behavior content for clusters and constructs by age group
 */
class BehaviorContentImport implements WithMultipleSheets, SkipsOnFailure
{
    use SkipsFailures;

    protected $errors = [];
    protected $successCount = 0;
    protected $failureCount = 0;
    protected $updatedClusters = 0;
    protected $updatedConstructs = 0;
    protected $type; // 'clusters', 'constructs', or null (auto-detect)

    /**
     * @param string|null $type 'clusters', 'constructs', or null for auto-detect
     */
    public function __construct($type = null)
    {
        $this->type = $type;
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
     * @return array
     * Process first sheet (index 0) using appropriate import based on type
     */
    public function sheets(): array
    {
        // If type is specified, use specific import
        if ($this->type === 'clusters') {
            return [
                0 => new ClustersBehaviorImport($this),
            ];
        } elseif ($this->type === 'constructs') {
            return [
                0 => new ConstructsBehaviorImport($this),
            ];
        }
        
        // Otherwise, use unified import that auto-detects
        return [
            0 => new UnifiedBehaviorImport($this),
        ];
    }

    /**
     * Get import statistics
     */
    public function getStats()
    {
        return [
            'success' => $this->successCount,
            'failures' => $this->failureCount,
            'updated_clusters' => $this->updatedClusters,
            'updated_constructs' => $this->updatedConstructs,
            'errors' => $this->errors,
        ];
    }

    public function addError($error)
    {
        $this->errors[] = $error;
        $this->failureCount++;
    }

    public function incrementSuccess()
    {
        $this->successCount++;
    }

    public function incrementClusterUpdate()
    {
        $this->updatedClusters++;
    }

    public function incrementConstructUpdate()
    {
        $this->updatedConstructs++;
    }
}

/**
 * Clusters Behavior Import Sheet
 */
class ClustersBehaviorImport implements ToCollection, WithHeadingRow
{
    protected $parent;

    public function __construct(BehaviorContentImport $parent)
    {
        $this->parent = $parent;
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
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            try {
                // Get required fields
                $id = isset($row['id']) ? (int)$row['id'] : null;
                $ageGroupId = isset($row['age_group_id']) ? (int)$row['age_group_id'] : null;

                if (!$id) {
                    $this->parent->addError([
                        'sheet' => 'Clusters',
                        'row' => is_array($row) ? $row : $row->toArray(),
                        'error' => 'Cluster ID is required'
                    ]);
                    continue;
                }

                // Find cluster
                $cluster = Cluster::find($id);
                if (!$cluster) {
                    $this->parent->addError([
                        'sheet' => 'Clusters',
                        'row' => is_array($row) ? $row : $row->toArray(),
                        'error' => "Cluster with ID {$id} not found"
                    ]);
                    continue;
                }

                // Validate age_group_id if provided
                if ($ageGroupId && $cluster->age_group_id != $ageGroupId) {
                    $this->parent->addError([
                        'sheet' => 'Clusters',
                        'row' => is_array($row) ? $row : $row->toArray(),
                        'error' => "Age Group ID mismatch. Cluster belongs to age group {$cluster->age_group_id}, but provided {$ageGroupId}"
                    ]);
                    continue;
                }

                // Update behavior fields if provided
                $updateData = [];
                
                if (isset($row['high_behaviour'])) {
                    $updateData['high_behaviour'] = $row['high_behaviour'];
                }
                
                if (isset($row['medium_behaviour'])) {
                    $updateData['medium_behaviour'] = $row['medium_behaviour'];
                }
                
                if (isset($row['low_behaviour'])) {
                    $updateData['low_behaviour'] = $row['low_behaviour'];
                }

                // Only update if there's data to update
                if (!empty($updateData)) {
                    $cluster->update($updateData);
                    $this->parent->incrementClusterUpdate();
                    $this->parent->incrementSuccess();
                }

            } catch (\Exception $e) {
                $this->parent->addError([
                    'sheet' => 'Clusters',
                    'row' => is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : []),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

/**
 * Constructs Behavior Import Sheet
 */
class ConstructsBehaviorImport implements ToCollection, WithHeadingRow
{
    protected $parent;

    public function __construct(BehaviorContentImport $parent)
    {
        $this->parent = $parent;
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
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            try {
                // Get required fields
                $id = isset($row['id']) ? (int)$row['id'] : null;
                $ageGroupId = isset($row['age_group_id']) ? (int)$row['age_group_id'] : null;

                if (!$id) {
                    $this->parent->addError([
                        'sheet' => 'Constructs',
                        'row' => is_array($row) ? $row : $row->toArray(),
                        'error' => 'Construct ID is required'
                    ]);
                    continue;
                }

                // Find construct
                $construct = Construct::find($id);
                if (!$construct) {
                    $this->parent->addError([
                        'sheet' => 'Constructs',
                        'row' => is_array($row) ? $row : $row->toArray(),
                        'error' => "Construct with ID {$id} not found"
                    ]);
                    continue;
                }

                // Validate age_group_id if provided
                if ($ageGroupId && $construct->age_group_id != $ageGroupId) {
                    $this->parent->addError([
                        'sheet' => 'Constructs',
                        'row' => is_array($row) ? $row : $row->toArray(),
                        'error' => "Age Group ID mismatch. Construct belongs to age group {$construct->age_group_id}, but provided {$ageGroupId}"
                    ]);
                    continue;
                }

                // Update behavior fields if provided
                $updateData = [];
                
                if (isset($row['high_behavior'])) {
                    $updateData['high_behavior'] = $row['high_behavior'];
                }
                
                if (isset($row['medium_behavior'])) {
                    $updateData['medium_behavior'] = $row['medium_behavior'];
                }
                
                if (isset($row['low_behavior'])) {
                    $updateData['low_behavior'] = $row['low_behavior'];
                }

                // Only update if there's data to update
                if (!empty($updateData)) {
                    $construct->update($updateData);
                    $this->parent->incrementConstructUpdate();
                    $this->parent->incrementSuccess();
                }

            } catch (\Exception $e) {
                $this->parent->addError([
                    'sheet' => 'Constructs',
                    'row' => is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : []),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

/**
 * Unified Behavior Import - handles both clusters and constructs in any sheet
 * Auto-detects type based on column headers
 */
class UnifiedBehaviorImport implements ToCollection, WithHeadingRow
{
    protected $parent;

    public function __construct(BehaviorContentImport $parent)
    {
        $this->parent = $parent;
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
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            return;
        }

        // Get first row to detect type based on headers
        $firstRow = $rows->first();
        $isCluster = isset($firstRow['high_behaviour']) || isset($firstRow['medium_behaviour']) || isset($firstRow['low_behaviour']);
        $isConstruct = isset($firstRow['high_behavior']) || isset($firstRow['medium_behavior']) || isset($firstRow['low_behavior']);

        foreach ($rows as $row) {
            try {
                $id = isset($row['id']) ? (int)$row['id'] : null;
                if (!$id) {
                    continue; // Skip rows without ID
                }

                // Determine if this is a cluster or construct row
                // Check for cluster-specific columns (high_behaviour) or construct-specific (high_behavior)
                $hasClusterFields = isset($row['high_behaviour']) || isset($row['medium_behaviour']) || isset($row['low_behaviour']);
                $hasConstructFields = isset($row['high_behavior']) || isset($row['medium_behavior']) || isset($row['low_behavior']);

                // Process as Cluster
                if ($hasClusterFields) {
                    $this->processCluster($row, $id);
                }
                // Process as Construct
                elseif ($hasConstructFields) {
                    $this->processConstruct($row, $id);
                }
                // If neither, try to determine by checking if ID exists in clusters or constructs
                else {
                    $cluster = Cluster::find($id);
                    $construct = Construct::find($id);
                    
                    if ($cluster && !$construct) {
                        $this->processCluster($row, $id);
                    } elseif ($construct && !$cluster) {
                        $this->processConstruct($row, $id);
                    }
                }

            } catch (\Exception $e) {
                $this->parent->addError([
                    'sheet' => 'Unified',
                    'row' => is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : []),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function processCluster($row, $id)
    {
        $ageGroupId = isset($row['age_group_id']) ? (int)$row['age_group_id'] : null;

        $cluster = Cluster::find($id);
        if (!$cluster) {
            $this->parent->addError([
                'sheet' => 'Clusters',
                'row' => is_array($row) ? $row : $row->toArray(),
                'error' => "Cluster with ID {$id} not found"
            ]);
            return;
        }

        if ($ageGroupId && $cluster->age_group_id != $ageGroupId) {
            $this->parent->addError([
                'sheet' => 'Clusters',
                'row' => is_array($row) ? $row : $row->toArray(),
                'error' => "Age Group ID mismatch. Cluster belongs to age group {$cluster->age_group_id}, but provided {$ageGroupId}"
            ]);
            return;
        }

        $updateData = [];
        if (isset($row['high_behaviour'])) {
            $updateData['high_behaviour'] = $row['high_behaviour'];
        }
        if (isset($row['medium_behaviour'])) {
            $updateData['medium_behaviour'] = $row['medium_behaviour'];
        }
        if (isset($row['low_behaviour'])) {
            $updateData['low_behaviour'] = $row['low_behaviour'];
        }

        if (!empty($updateData)) {
            $cluster->update($updateData);
            $this->parent->incrementClusterUpdate();
            $this->parent->incrementSuccess();
        }
    }

    protected function processConstruct($row, $id)
    {
        $ageGroupId = isset($row['age_group_id']) ? (int)$row['age_group_id'] : null;

        $construct = Construct::find($id);
        if (!$construct) {
            $this->parent->addError([
                'sheet' => 'Constructs',
                'row' => is_array($row) ? $row : $row->toArray(),
                'error' => "Construct with ID {$id} not found"
            ]);
            return;
        }

        if ($ageGroupId && $construct->age_group_id != $ageGroupId) {
            $this->parent->addError([
                'sheet' => 'Constructs',
                'row' => is_array($row) ? $row : $row->toArray(),
                'error' => "Age Group ID mismatch. Construct belongs to age group {$construct->age_group_id}, but provided {$ageGroupId}"
            ]);
            return;
        }

        $updateData = [];
        if (isset($row['high_behavior'])) {
            $updateData['high_behavior'] = $row['high_behavior'];
        }
        if (isset($row['medium_behavior'])) {
            $updateData['medium_behavior'] = $row['medium_behavior'];
        }
        if (isset($row['low_behavior'])) {
            $updateData['low_behavior'] = $row['low_behavior'];
        }

        if (!empty($updateData)) {
            $construct->update($updateData);
            $this->parent->incrementConstructUpdate();
            $this->parent->incrementSuccess();
        }
    }
}
