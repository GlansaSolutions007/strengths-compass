<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            ['name' => 'English', 'code' => 'en', 'is_active' => true],
            ['name' => 'Telugu', 'code' => 'te', 'is_active' => true],
            ['name' => 'Hindi', 'code' => 'hi', 'is_active' => true],
            ['name' => 'Tamil', 'code' => 'ta', 'is_active' => true],
            ['name' => 'Kannada', 'code' => 'kn', 'is_active' => true],
            ['name' => 'Malayalam', 'code' => 'ml', 'is_active' => true],
        ];

        foreach ($languages as $language) {
            // Only insert if the language code does not already exist
            \DB::table('languages')->updateOrCreate(
                ['code' => $language['code']], // Search condition
                $language // Data to insert/update
            );
        }
    }
}
