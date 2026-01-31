<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Font;

class RawTestDataSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->styleHeaderRow($sheet);
                $this->mergeClusterAndConstructCells($sheet);
                $this->applyLeftAlignment($sheet);
            },
        ];
    }

    /**
     * Left-align all values in the sheet.
     */
    private function applyLeftAlignment(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        if ($highestRow >= 1) {
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                ->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        }
    }

    /**
     * Make the header row bold
     */
    private function styleHeaderRow(Worksheet $sheet): void
    {
        $highestColumn = $sheet->getHighestColumn();
        
        // Make header row (row 1) bold
        $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
        
        // Optionally, you can also add background color or other styling
        // $sheet->getStyle("A1:{$highestColumn}1")->getFill()
        //     ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        //     ->getStartColor()->setARGB('FFE0E0E0');
    }

    /**
     * Merge cells for Cluster and Construct columns where values repeat
     */
    private function mergeClusterAndConstructCells(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        
        // Track cluster and construct ranges
        $clusterStartRow = null;
        $clusterValue = null;
        $constructStartRow = null;
        $constructValue = null;

        // Start from row 2 (skip header row)
        for ($row = 2; $row <= $highestRow; $row++) {
            $clusterCell = $sheet->getCell("A{$row}")->getValue();
            $constructCell = $sheet->getCell("B{$row}")->getValue();

            // Skip empty rows (like separator rows)
            if ($clusterCell === null && $constructCell === null) {
                // Merge previous ranges before separator
                if ($clusterStartRow !== null && $clusterValue !== null) {
                    $this->mergeRange($sheet, "A{$clusterStartRow}:A" . ($row - 1), $clusterValue);
                }
                if ($constructStartRow !== null && $constructValue !== null) {
                    $this->mergeRange($sheet, "B{$constructStartRow}:B" . ($row - 1), $constructValue);
                }
                $clusterStartRow = null;
                $clusterValue = null;
                $constructStartRow = null;
                $constructValue = null;
                continue;
            }

            // Handle Cluster column (A)
            if ($clusterCell !== null && $clusterCell !== '') {
                if ($clusterValue === null || $clusterCell !== $clusterValue) {
                    // Merge previous range if exists
                    if ($clusterStartRow !== null && $clusterValue !== null && $clusterStartRow < $row - 1) {
                        $this->mergeRange($sheet, "A{$clusterStartRow}:A" . ($row - 1), $clusterValue);
                    }
                    // Start new range
                    $clusterStartRow = $row;
                    $clusterValue = $clusterCell;
                }
            } else {
                // Empty cell, merge previous range
                if ($clusterStartRow !== null && $clusterValue !== null && $clusterStartRow < $row - 1) {
                    $this->mergeRange($sheet, "A{$clusterStartRow}:A" . ($row - 1), $clusterValue);
                }
                $clusterStartRow = null;
                $clusterValue = null;
            }

            // Handle Construct column (B)
            if ($constructCell !== null && $constructCell !== '') {
                if ($constructValue === null || $constructCell !== $constructValue) {
                    // Merge previous range if exists
                    if ($constructStartRow !== null && $constructValue !== null && $constructStartRow < $row - 1) {
                        $this->mergeRange($sheet, "B{$constructStartRow}:B" . ($row - 1), $constructValue);
                    }
                    // Start new range
                    $constructStartRow = $row;
                    $constructValue = $constructCell;
                }
            } else {
                // Empty cell, merge previous range
                if ($constructStartRow !== null && $constructValue !== null && $constructStartRow < $row - 1) {
                    $this->mergeRange($sheet, "B{$constructStartRow}:B" . ($row - 1), $constructValue);
                }
                $constructStartRow = null;
                $constructValue = null;
            }
        }

        // Merge final ranges
        if ($clusterStartRow !== null && $clusterValue !== null) {
            $this->mergeRange($sheet, "A{$clusterStartRow}:A{$highestRow}", $clusterValue);
        }
        if ($constructStartRow !== null && $constructValue !== null) {
            $this->mergeRange($sheet, "B{$constructStartRow}:B{$highestRow}", $constructValue);
        }
    }

    /**
     * Merge a range of cells and set the value
     */
    private function mergeRange(Worksheet $sheet, string $range, $value): void
    {
        $sheet->mergeCells($range);
        $sheet->getCell(explode(':', $range)[0])->setValue($value);
        
        // Left align vertically centered for merged cells
        $sheet->getStyle($range)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    }
}

