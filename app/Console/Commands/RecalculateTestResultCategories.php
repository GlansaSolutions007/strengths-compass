<?php

namespace App\Console\Commands;

use App\Models\TestResult;
use Illuminate\Console\Command;

class RecalculateTestResultCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-results:recalculate-categories 
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate high/medium/low categories for existing test results using new thresholds (60-75=medium, 76-100=high)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be saved.');
        }

        $query = TestResult::query()->where('status', 'completed');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $testResults = $query->get();
        $total = $testResults->count();

        if ($total === 0) {
            $this->info('No test results to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$total} test result(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        /** @var TestResult $testResult */
        foreach ($testResults as $testResult) {
            $changes = $this->recalculateCategories($testResult);

            if ($changes && ! $dryRun) {
                $testResult->overall_category = $changes['overall_category'];
                $testResult->cluster_scores = $changes['cluster_scores'];
                $testResult->construct_scores = $changes['construct_scores'];
                $testResult->save();
                $updated++;
            } elseif ($changes && $dryRun) {
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Would update {$updated} test result(s).");
        } else {
            $this->info("Updated {$updated} test result(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Recalculate categories using new thresholds: 0-59=low, 60-75=medium, 76-100=high
     */
    private function recalculateCategories(TestResult $testResult): ?array
    {
        $clusterScores = $testResult->getRawOriginal('cluster_scores');
        $constructScores = $testResult->getRawOriginal('construct_scores');

        if (is_string($clusterScores)) {
            $clusterScores = json_decode($clusterScores, true) ?? [];
        }
        if (is_string($constructScores)) {
            $constructScores = json_decode($constructScores, true) ?? [];
        }

        if (! is_array($clusterScores)) {
            $clusterScores = [];
        }
        if (! is_array($constructScores)) {
            $constructScores = [];
        }

        $hasChanges = false;

        // Recalculate overall_category from average_score
        $averageScore = $testResult->average_score;
        $newOverallCategory = $this->categorizeFromAverage($averageScore);
        if (($testResult->overall_category ?? '') !== $newOverallCategory) {
            $hasChanges = true;
        }

        // Recalculate category for each cluster
        foreach ($clusterScores as $clusterName => &$scoreData) {
            if (! is_array($scoreData)) {
                continue;
            }
            $average = $scoreData['average'] ?? 0;
            $newCategory = $this->categorizeFromAverage($average);
            if (($scoreData['category'] ?? null) !== $newCategory) {
                $scoreData['category'] = $newCategory;
                $hasChanges = true;
            }
        }

        // Recalculate category for each construct
        foreach ($constructScores as $constructName => &$scoreData) {
            if (! is_array($scoreData)) {
                continue;
            }
            $average = $scoreData['average'] ?? 0;
            $newCategory = $this->categorizeFromAverage($average);
            if (($scoreData['category'] ?? null) !== $newCategory) {
                $scoreData['category'] = $newCategory;
                $hasChanges = true;
            }
        }

        if (! $hasChanges) {
            return null;
        }

        return [
            'overall_category' => $newOverallCategory,
            'cluster_scores' => $clusterScores,
            'construct_scores' => $constructScores,
        ];
    }

    /**
     * Categorize based on average score (1-5 scale)
     * Thresholds: 0-59% = low, 60-75% = medium, 76-100% = high
     */
    private function categorizeFromAverage(?float $average): string
    {
        if ($average === null || $average <= 0) {
            return 'low';
        }

        $percentage = (($average - 1) / 4) * 100;

        if ($percentage < 60) {
            return 'low';
        }
        if ($percentage < 76) {
            return 'medium';
        }

        return 'high';
    }
}
