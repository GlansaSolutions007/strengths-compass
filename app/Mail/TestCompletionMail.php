<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Test;
use App\Models\TestResult;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class TestCompletionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Test $test,
        public TestResult $testResult
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            subject: 'Test Completed Successfully - Strengths Compass',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.test-completion',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        try {
            // Generate short PDF report
            $pdf = App::make('dompdf.wrapper');
            
            // Load test result with relationships
            $testResult = TestResult::with([
                'user',
                'test.clusters.constructs',
            ])->find($this->testResult->id);

            if (!$testResult) {
                return [];
            }

            // Calculate cluster insights and construct details
            $clusterInsights = $this->calculateClusterInsights($testResult->cluster_scores ?? []);
            $clusterDetails = $this->buildClusterDetails($testResult);
            $constructDetails = $this->buildConstructDetails($clusterDetails);

            // Calculate construct scores with bands
            $constructScoresWithBands = [];
            if (!empty($constructDetails) && !empty($testResult->construct_scores)) {
                foreach ($constructDetails as $construct) {
                    $constructName = $construct['name'];
                    $constructScore = $testResult->construct_scores[$constructName] ?? null;
                    
                    if ($constructScore !== null) {
                        $average = is_array($constructScore) ? ($constructScore['average'] ?? 0) : (float) $constructScore;
                        $percentage = $this->convertScoreToPercentage($average);
                        $band = $this->getStrengthCategory($percentage);
                        
                        $constructScoresWithBands[] = array_merge($construct, [
                            'percentage' => $percentage,
                            'band' => $band,
                        ]);
                    }
                }
            }

            // Prepare data for PDF
            $data = [
                'testResult' => $testResult,
                'user' => $testResult->user,
                'test' => $testResult->test,
                'clusterInsights' => $clusterInsights,
                'constructDetails' => $constructScoresWithBands,
            ];

            $pdf->loadView('reports.short-report', $data);
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('enable-local-file-access', true);
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', false);

            $pdfContent = $pdf->output();
            $filename = 'strengths-compass-report-' . $testResult->id . '.pdf';

            return [
                Attachment::fromData(fn () => $pdfContent, $filename)
                    ->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF attachment for email', [
                'test_result_id' => $this->testResult->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Calculate cluster insights (helper method)
     */
    private function calculateClusterInsights($clusterScores): array
    {
        if (empty($clusterScores) || !is_array($clusterScores)) {
            return [];
        }

        $insights = [];

        foreach ($clusterScores as $name => $score) {
            $average = null;

            if (is_array($score)) {
                if (isset($score['average'])) {
                    $average = (float) $score['average'];
                } elseif (isset($score['total'], $score['count']) && (float) $score['count'] > 0) {
                    $average = (float) $score['total'] / (float) $score['count'];
                }
            } else {
                $average = (float) $score;
            }

            if ($average === null) {
                continue;
            }

            $percentage = $this->convertScoreToPercentage($average);

            $insights[] = [
                'name' => $name,
                'average' => round($average, 2),
                'percentage' => $percentage,
                'strength_band' => $this->getStrengthCategory($percentage),
            ];
        }

        return $insights;
    }

    /**
     * Convert 1-5 average to percentage (0-100)
     */
    private function convertScoreToPercentage(?float $average): int
    {
        if ($average === null) {
            return 0;
        }

        $shifted = max(0, min(4, $average - 1));
        $normalized = $shifted / 4;
        $percentage = round($normalized * 100);

        return max(0, min(100, (int) $percentage));
    }

    /**
     * Determine strength category by percentage
     */
    private function getStrengthCategory(int $percentage): string
    {
        if ($percentage >= 80) {
            return 'High';
        }

        if ($percentage >= 60) {
            return 'Medium';
        }

        return 'Low';
    }

    /**
     * Build cluster details
     */
    private function buildClusterDetails(TestResult $testResult): array
    {
        if (!$testResult->test) {
            return [];
        }

        $clusters = $testResult->test->clusters ?? collect();

        if ($clusters->isEmpty()) {
            return [];
        }

        return $clusters->map(function ($cluster) {
            return [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'description' => $cluster->description,
                'constructs' => $cluster->constructs
                    ? $cluster->constructs->map(function ($construct) {
                        return [
                            'id' => $construct->id,
                            'name' => $construct->name,
                            'description' => $construct->description ?? $construct->definition,
                        ];
                    })->values()->all()
                    : [],
            ];
        })->values()->all();
    }

    /**
     * Build construct details
     */
    private function buildConstructDetails(array $clusterDetails): array
    {
        if (empty($clusterDetails)) {
            return [];
        }

        $constructs = [];

        foreach ($clusterDetails as $cluster) {
            if (empty($cluster['constructs'])) {
                continue;
            }

            foreach ($cluster['constructs'] as $construct) {
                $constructs[] = array_merge($construct, [
                    'cluster_name' => $cluster['name'],
                ]);
            }
        }

        return $constructs;
    }
}

