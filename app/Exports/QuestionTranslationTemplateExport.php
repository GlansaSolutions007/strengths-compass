<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class QuestionTranslationTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    /**
     * @param  Collection  $questions
     */
    public function __construct(
        protected Collection $questions
    ) {
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return $this->questions->map(function ($question) {
            return [
                'question_id' => $question->id,
                'question_en' => $question->question_text,
                'language' => '', // Empty for translators to fill
                'translated_text' => '', // Empty for translators to fill
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'question_id',
            'question_en',
            'language',
            'translated_text',
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
            },
        ];
    }
}
