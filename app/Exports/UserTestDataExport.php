<?php

namespace App\Exports;

use App\Exports\Sheets\ClusterSummarySheet;
use App\Exports\Sheets\ConstructSummarySheet;
use App\Exports\Sheets\RawTestDataSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserTestDataExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<string, mixed>>|array<int, array{test_id: mixed, test_title: string, raw_data: array}>  $data
     * @param  array<int, array<string, mixed>>  $clusterData
     * @param  array<int, array<string, mixed>>  $constructData
     */
    public function __construct(
        protected array $data,
        protected array $clusterData = [],
        protected array $constructData = []
    ) {
    }

    /**
     * @return array<int, \Maatwebsite\Excel\Concerns\WithTitle&\Maatwebsite\Excel\Concerns\FromArray>
     */
    public function sheets(): array
    {
        $sheets = [];

        // Check if data is grouped by test (new format) or single dataset (old format for backward compatibility)
        $isGroupedByTest = !empty($this->data) && isset($this->data[0]['test_id']);

        if ($isGroupedByTest) {
            // New format: Multiple tests, each in its own sheet
            foreach ($this->data as $testDataset) {
                $testTitle = $this->sanitizeSheetName($testDataset['test_title'] ?? "Test {$testDataset['test_id']}");
                $rawData = $testDataset['raw_data'] ?? [];
                
                if (!empty($rawData)) {
                    $sheets[] = new RawTestDataSheet($rawData, $testTitle);
                }
            }
        } else {
            // Old format: Single sheet (backward compatibility)
            $sheets[] = new RawTestDataSheet($this->data);
        }

        // Only include cluster and construct sheets if data is provided
        if (!empty($this->clusterData)) {
            $sheets[] = new ClusterSummarySheet($this->clusterData);
        }

        if (!empty($this->constructData)) {
            $sheets[] = new ConstructSummarySheet($this->constructData);
        }

        return $sheets;
    }

    /**
     * Sanitize sheet name to comply with Excel limitations
     * Excel sheet names can be max 31 characters and cannot contain certain characters
     */
    private function sanitizeSheetName(string $name): string
    {
        // Remove invalid characters: \ / ? * [ ]
        $name = preg_replace('/[\\\\\/\?\*\[\]]/', '', $name);
        
        // Truncate to 31 characters (Excel limit)
        if (strlen($name) > 31) {
            $name = substr($name, 0, 31);
        }
        
        return $name ?: 'Sheet';
    }
}

