<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class StatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = 'C:\Users\LENOVO\Downloads\states.sql';

        if (!File::exists($sqlFile)) {
            $this->command->error('States SQL file not found at: ' . $sqlFile);
            return;
        }

        // Check if states table already has data
        if (DB::table('states')->count() > 0) {
            $this->command->info('States table already contains data. Skipping seed.');
            return;
        }

        // Read the SQL file
        $sql = File::get($sqlFile);
        
        // Extract the INSERT statement (handle multiline)
        if (preg_match('/INSERT INTO `states`[^;]+;/s', $sql, $matches)) {
            $insertStatement = $matches[0];
            
            // Remove backticks for table and column names
            $insertStatement = preg_replace('/`([^`]+)`/', '$1', $insertStatement);
            
            // Execute the INSERT statement directly
            try {
                DB::statement($insertStatement);
                $this->command->info('States data imported successfully.');
            } catch (\Exception $e) {
                $this->command->error('Error importing states data: ' . $e->getMessage());
            }
        } else {
            $this->command->error('Could not find INSERT statement in SQL file.');
        }
    }
}
