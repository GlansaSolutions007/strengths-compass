<?php

namespace App\Exports;

use App\Exports\Sheets\ClusterSummarySheet;
use App\Exports\Sheets\ConstructSummarySheet;
use App\Exports\Sheets\RawTestDataSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserTestDataExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<string, mixed>>  $rawData
     * @param  array<int, array<string, mixed>>  $clusterData
     * @param  array<int, array<string, mixed>>  $constructData
     */
    public function __construct(
        protected array $rawData,
        protected array $clusterData = [],
        protected array $constructData = []
    ) {
    }

    /**
     * @return array<int, \Maatwebsite\Excel\Concerns\WithTitle&\Maatwebsite\Excel\Concerns\FromArray>
     */
    public function sheets(): array
    {
        $sheets = [
            new RawTestDataSheet($this->rawData),
        ];

        // Only include cluster and construct sheets if data is provided
        if (!empty($this->clusterData)) {
            $sheets[] = new ClusterSummarySheet($this->clusterData);
        }

        if (!empty($this->constructData)) {
            $sheets[] = new ConstructSummarySheet($this->constructData);
        }

        return $sheets;
    }
}

