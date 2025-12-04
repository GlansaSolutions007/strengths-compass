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
        protected array $clusterData,
        protected array $constructData
    ) {
    }

    /**
     * @return array<int, \Maatwebsite\Excel\Concerns\WithTitle&\Maatwebsite\Excel\Concerns\FromArray>
     */
    public function sheets(): array
    {
        return [
            new RawTestDataSheet($this->rawData),
            new ClusterSummarySheet($this->clusterData),
            new ConstructSummarySheet($this->constructData),
        ];
    }
}

