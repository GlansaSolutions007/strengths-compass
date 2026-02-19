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
     * Cluster and construct are independent: template lists all constructs for age group.
     * User assigns cluster per row in the Excel (cluster-construct is per-test when importing).
     */
    public function collection()
    {
        $constructQuery = Construct::where('is_active', true)
            ->where('is_deleted', false);

        if ($this->ageGroupId) {
            $constructQuery->where(function ($q) {
                $q->where('age_group_id', $this->ageGroupId)->orWhereNull('age_group_id');
            });
        }

        $constructs = $constructQuery->get();
        $rows = [];

        foreach ($constructs as $construct) {
            $rows[] = [
                'cluster' => '', // User fills: cluster for this test
                'construct' => $construct->name,
                'question' => '',
                'category' => '',
                'question_id' => '', // Optional: for CERC, paste existing question ID to reuse (no duplicate)
            ];
        }

        if (empty($rows)) {
            $clusterQuery = Cluster::where('is_active', true)->where('is_deleted', false);
            if ($this->ageGroupId) {
                $clusterQuery->where('age_group_id', $this->ageGroupId);
            }
            foreach ($clusterQuery->get() as $cluster) {
                $rows[] = [
                    'cluster' => $cluster->name,
                    'construct' => '',
                    'question' => '',
                    'category' => '',
                    'question_id' => '',
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
            'Question ID', // Optional: for CERC, use existing question ID to reuse (prevents duplicate)
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
                $sheet->getStyle('A1:E1')->getFont()->setBold(true);
                
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
                    $sheet->setCellValue('G1', 'Age Group: ' . $this->ageGroup->name);
                    $sheet->getStyle('G1')->getFont()->setBold(true)->setItalic(true);
                }
            },
        ];
    }
}

