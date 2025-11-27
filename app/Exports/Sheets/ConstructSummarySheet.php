<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConstructSummarySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'User ID',
            'User Name',
            'Email',
            'Test ID',
            'Test Title',
            'Construct Name',
            'Total Score',
            'Average Score',
            'Percentage',
            'Category',
            'Question Count',
            'Submitted At',
        ];
    }

    public function title(): string
    {
        return 'Construct Summary';
    }
}

