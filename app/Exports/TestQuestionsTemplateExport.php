<?php

namespace App\Exports;

use App\Models\Cluster;
use App\Models\Construct;
use App\Models\AgeGroup;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;

class TestQuestionsTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    protected $ageGroupId;
    protected $ageGroup;

    public function __construct($ageGroupId = null)
    {
        $this->ageGroupId = $ageGroupId;
        if ($ageGroupId) {
            $this->ageGroup = AgeGroup::find($ageGroupId);
        }
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        $query = Cluster::with(['constructs', 'ageGroup'])
            ->where('is_active', true)
            ->where('is_deleted', false);

        if ($this->ageGroupId) {
            $query->where('age_group_id', $this->ageGroupId);
        }

        $clusters = $query->get();
        $rows = [];

        // Create rows with cluster and construct names prefilled
        // Each row represents a potential question entry
        foreach ($clusters as $cluster) {
            // Access constructs as a relationship property and filter
            $constructs = $cluster->constructs->filter(function ($construct) {
                return $construct->is_active && !$construct->is_deleted;
            });

            // If cluster has constructs, create one row per construct
            if ($constructs->count() > 0) {
                foreach ($constructs as $construct) {
                    $rows[] = [
                        'cluster' => $cluster->name,
                        'construct' => $construct->name,
                        'question' => '', // Empty for admin to fill
                        'category' => '', // Empty for admin to fill (P, R, or SDB)
                    ];
                }
            } else {
                // If cluster has no constructs, still create a row with cluster name
                $rows[] = [
                    'cluster' => $cluster->name,
                    'construct' => '', // Empty if no constructs exist
                    'question' => '', // Empty for admin to fill
                    'category' => '', // Empty for admin to fill (P, R, or SDB)
                ];
            }
        }

        return collect($rows);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Cluster',
            'Construct',
            'Question',
            'Category',
        ];
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Make header row bold
                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                
                // Freeze first row
                $sheet->freezePane('A2');

                // Add data validation for Category column (D column)
                $lastRow = $sheet->getHighestRow();
                if ($lastRow > 1) {
                    // Create dropdown for Category column with values: P, R, SDB
                    $validation = $sheet->getCell('D2')->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Invalid Category');
                    $validation->setError('Category must be P, R, or SDB');
                    $validation->setPromptTitle('Select Category');
                    $validation->setPrompt('Please select a category: P, R, or SDB');
                    $validation->setFormula1('"P,R,SDB"');

                    // Apply validation to all rows in column D
                    for ($row = 2; $row <= $lastRow; $row++) {
                        $cell = $sheet->getCell("D{$row}");
                        $cell->setDataValidation(clone $validation);
                    }
                }

                // Add age group info as a note if age group is specified
                if ($this->ageGroup) {
                    $sheet->setCellValue('F1', 'Age Group: ' . $this->ageGroup->name);
                    $sheet->getStyle('F1')->getFont()->setBold(true)->setItalic(true);
                }
            },
        ];
    }
}

