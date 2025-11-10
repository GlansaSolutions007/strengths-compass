<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OptionsModel;

class OptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            ['label' => 'Strongly Disagree', 'value' => 1],
            ['label' => 'Disagree', 'value' => 2],
            ['label' => 'Neutral', 'value' => 3],
            ['label' => 'Agree', 'value' => 4],
            ['label' => 'Strongly Agree', 'value' => 5],
        ];

        foreach ($options as $option) {
            OptionsModel::updateOrCreate(
                ['value' => $option['value']],
                ['label' => $option['label']]
            );
        }
    }
}
