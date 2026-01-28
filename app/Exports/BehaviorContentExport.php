<?php

namespace App\Exports;

use App\Models\Cluster;
use App\Models\Construct;
use App\Models\AgeGroup;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class BehaviorContentExport implements WithMultipleSheets
{
    protected $ageGroupId;
    protected $type; // 'clusters', 'constructs', or null (both)

    public function __construct($ageGroupId = null, $type = null)
    {
        $this->ageGroupId = $ageGroupId;
        $this->type = $type;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        
        // If type is specified, export only that type
        if ($this->type === 'clusters') {
            $sheets[] = new ClustersBehaviorSheet($this->ageGroupId);
        } elseif ($this->type === 'constructs') {
            $sheets[] = new ConstructsBehaviorSheet($this->ageGroupId);
        } else {
            // Export both if type is not specified
            $sheets[] = new ClustersBehaviorSheet($this->ageGroupId);
            $sheets[] = new ConstructsBehaviorSheet($this->ageGroupId);
        }
        
        return $sheets;
    }
}

/**
 * Clusters Behavior Content Sheet
 */
class ClustersBehaviorSheet implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
{
    protected $ageGroupId;

    public function __construct($ageGroupId = null)
    {
        $this->ageGroupId = $ageGroupId;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Cluster::with('ageGroup');

        if ($this->ageGroupId) {
            $query->where('age_group_id', $this->ageGroupId);
        }

        return $query->get()->map(function ($cluster) {
            return [
                'id' => $cluster->id,
                'age_group_id' => $cluster->age_group_id,
                'age_group_name' => $cluster->ageGroup ? $cluster->ageGroup->name : '',
                'name' => $cluster->name,
                'short_code' => $cluster->short_code ?? '',
                'high_behaviour' => $cluster->high_behaviour ?? '',
                'medium_behaviour' => $cluster->medium_behaviour ?? '',
                'low_behaviour' => $cluster->low_behaviour ?? '',
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'id',
            'age_group_id',
            'age_group_name',
            'name',
            'short_code',
            'high_behaviour',
            'medium_behaviour',
            'low_behaviour',
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Clusters';
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function(\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Make header row bold
                $sheet->getStyle('A1:H1')->getFont()->setBold(true);
                
                // Freeze first row
                $sheet->freezePane('A2');
            },
        ];
    }
}

/**
 * Constructs Behavior Content Sheet
 */
class ConstructsBehaviorSheet implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithTitle, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
{
    protected $ageGroupId;

    public function __construct($ageGroupId = null)
    {
        $this->ageGroupId = $ageGroupId;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Construct::with(['ageGroup', 'cluster']);

        if ($this->ageGroupId) {
            $query->where('age_group_id', $this->ageGroupId);
        }

        return $query->get()->map(function ($construct) {
            return [
                'id' => $construct->id,
                'age_group_id' => $construct->age_group_id,
                'age_group_name' => $construct->ageGroup ? $construct->ageGroup->name : '',
                'cluster_id' => $construct->cluster_id,
                'cluster_name' => $construct->cluster ? $construct->cluster->name : '',
                'name' => $construct->name,
                'short_code' => $construct->short_code ?? '',
                'high_behavior' => $construct->high_behavior ?? '',
                'medium_behavior' => $construct->medium_behavior ?? '',
                'low_behavior' => $construct->low_behavior ?? '',
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'id',
            'age_group_id',
            'age_group_name',
            'cluster_id',
            'cluster_name',
            'name',
            'short_code',
            'high_behavior',
            'medium_behavior',
            'low_behavior',
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Constructs';
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function(\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Make header row bold
                $sheet->getStyle('A1:J1')->getFont()->setBold(true);
                
                // Freeze first row
                $sheet->freezePane('A2');
            },
        ];
    }
}
