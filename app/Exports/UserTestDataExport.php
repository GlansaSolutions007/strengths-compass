<?php

namespace App\Exports;

use App\Exports\Sheets\RawTestDataSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserTestDataExport implements WithMultipleSheets
{
    public function __construct(protected array $rawData)
    {
    }

    /**
     * @return array<int, \Maatwebsite\Excel\Concerns\WithTitle&\Maatwebsite\Excel\Concerns\FromArray>
     */
    public function sheets(): array
    {
        return [
            new RawTestDataSheet($this->rawData),
        ];
    }
}

