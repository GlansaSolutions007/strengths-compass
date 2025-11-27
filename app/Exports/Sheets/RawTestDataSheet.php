<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RawTestDataSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(protected array $rows)
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
            'Contact Number',
            'WhatsApp Number',
            'Gender',
            'Age',
            'City',
            'State',
            'Country',
            'Profession',
            'Educational Qualification',
            'Test ID',
            'Test Title',
            'Submitted At',
            'Cluster Name',
            'Cluster Percentage',
            'Cluster Category',
            'Construct Name',
            'Construct Percentage',
            'Construct Category',
            'Question ID',
            'Question Text',
            'Question Category',
            'Answer Value',
            'Answer Label',
            'Final Score',
        ];
    }

    public function title(): string
    {
        return 'Raw Data';
    }
}

