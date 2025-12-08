<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class RawTestDataSheet implements FromArray, WithTitle, ShouldAutoSize
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  string|null  $sheetTitle
     */
    public function __construct(
        protected array $rows,
        protected ?string $sheetTitle = null
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->sheetTitle ?? 'Raw Data';
    }
}

