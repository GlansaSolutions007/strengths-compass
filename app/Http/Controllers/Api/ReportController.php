<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Models\TestReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Get report for a test result
     * Returns the report data (if exists) or creates a new one
     */
    public function getReport($testResultId)
    {
        $testResult = TestResult::with(['user', 'test', 'report'])->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Test result not found',
            ], 404);
        }

        // Get or create report
        $report = $testResult->report;
        
        if (!$report) {
            // Create a new report record (content will be set later)
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'generated_at' => now(),
            ]);
        }

        // Load report with test result and its relationships
        $report->load(['testResult.user', 'testResult.test']);

        $clusterInsights = $this->calculateClusterInsights($testResult->cluster_scores ?? []);
        $radarChart = $this->buildRadarChartData($clusterInsights);

        return response()->json([
            'data' => [
                'report' => $report,
                'cluster_insights' => $clusterInsights,
                'radar_chart' => $radarChart,
            ],
            'status' => 200,
            'message' => 'Report retrieved successfully',
        ], 200);
    }

    /**
     * Generate and download PDF report
     */
    public function downloadPdf($testResultId)
    {
        $testResult = TestResult::with(['user', 'test', 'report'])->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Test result not found',
            ], 404);
        }

        // Get or create report
        $report = $testResult->report;
        
        if (!$report) {
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'generated_at' => now(),
            ]);
        }

        $clusterInsights = $this->calculateClusterInsights($testResult->cluster_scores ?? []);
        $radarChart = $this->buildRadarChartData($clusterInsights);

        // Prepare data for PDF
        $data = [
            'testResult' => $testResult,
            'report' => $report,
            'user' => $testResult->user,
            'test' => $testResult->test,
            'clusterScores' => $testResult->cluster_scores,
            'constructScores' => $testResult->construct_scores,
            'totalScore' => $testResult->total_score,
            'averageScore' => $testResult->average_score,
            'clusterInsights' => $clusterInsights,
            'radarChartData' => $radarChart,
        ];

        // Generate PDF using container binding (more reliable than facade)
        try {
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadView('reports.test-report', $data);
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('enable-local-file-access', true);
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', false);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'PDF library not available. Please ensure barryvdh/laravel-dompdf is installed and run: composer dump-autoload',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Generate filename
        $filename = 'test-report-' . $testResult->id . '-' . now()->format('Y-m-d') . '.pdf';

        // Get PDF output
        $pdfOutput = $pdf->output();

        // Save PDF to storage (optional - for later retrieval)
        $pdfPath = 'reports/' . $filename;
        Storage::disk('public')->put($pdfPath, $pdfOutput);

        // Update report with file path
        $report->update([
            'report_file' => $pdfPath,
            'generated_at' => now(),
        ]);

        // Return PDF download response with proper headers
        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($pdfOutput));
    }

    /**
     * View PDF report in browser
     */
    public function viewPdf($testResultId)
    {
        $testResult = TestResult::with(['user', 'test', 'report'])->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Test result not found',
            ], 404);
        }

        // Get or create report
        $report = $testResult->report;
        
        if (!$report) {
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'generated_at' => now(),
            ]);
        }

        $clusterInsights = $this->calculateClusterInsights($testResult->cluster_scores ?? []);
        $radarChart = $this->buildRadarChartData($clusterInsights);

        // Prepare data for PDF
        $data = [
            'testResult' => $testResult,
            'report' => $report,
            'user' => $testResult->user,
            'test' => $testResult->test,
            'clusterScores' => $testResult->cluster_scores,
            'constructScores' => $testResult->construct_scores,
            'totalScore' => $testResult->total_score,
            'averageScore' => $testResult->average_score,
            'clusterInsights' => $clusterInsights,
            'radarChartData' => $radarChart,
        ];

        // Generate PDF using container binding (more reliable than facade)
        try {
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadView('reports.test-report', $data);
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('enable-local-file-access', true);
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', false);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'PDF library not available. Please ensure barryvdh/laravel-dompdf is installed and run: composer dump-autoload',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Return PDF stream (view in browser)
        return $pdf->stream('test-report-' . $testResult->id . '.pdf');
    }

    /**
     * Store PDF file uploaded from frontend
     * Accepts PDF as file upload or base64 encoded string
     */
    public function storePdf(Request $request, $testResultId)
    {
        $testResult = TestResult::with(['report'])->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Test result not found',
            ], 404);
        }

        // Validate request - accept either file upload or base64 string
        $validator = Validator::make($request->all(), [
            'pdf_file' => 'sometimes|file|mimes:pdf|max:10240', // 10MB max for file upload
            'pdf_base64' => 'sometimes|string', // Base64 encoded PDF
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $pdfContent = null;
            $filename = 'test-report-' . $testResult->id . '-' . now()->format('Y-m-d-His') . '.pdf';

            // Handle file upload
            if ($request->hasFile('pdf_file')) {
                $file = $request->file('pdf_file');
                $pdfContent = file_get_contents($file->getRealPath());
                $filename = $file->getClientOriginalName() ?: $filename;
            }
            // Handle base64 encoded PDF
            elseif ($request->has('pdf_base64')) {
                $base64String = $request->input('pdf_base64');
                
                // Remove data URL prefix if present (data:application/pdf;base64,)
                if (strpos($base64String, ',') !== false) {
                    $base64String = explode(',', $base64String)[1];
                }
                
                $pdfContent = base64_decode($base64String);
                
                // Validate it's actually a PDF
                if (substr($pdfContent, 0, 4) !== '%PDF') {
                    return response()->json([
                        'data' => [],
                        'status' => 422,
                        'message' => 'Invalid PDF file. The base64 string does not contain a valid PDF.',
                    ], 422);
                }
            } else {
                return response()->json([
                    'data' => [],
                    'status' => 422,
                    'message' => 'Either pdf_file or pdf_base64 must be provided',
                ], 422);
            }

            if (!$pdfContent) {
                return response()->json([
                    'data' => [],
                    'status' => 422,
                    'message' => 'Failed to process PDF file',
                ], 422);
            }

            // Get or create report
            $report = $testResult->report;
            
            if (!$report) {
                $report = TestReport::create([
                    'test_result_id' => $testResult->id,
                    'generated_at' => now(),
                ]);
            }

            // Delete old PDF if exists
            if ($report->report_file && Storage::disk('public')->exists($report->report_file)) {
                Storage::disk('public')->delete($report->report_file);
            }

            // Store PDF file
            $pdfPath = 'reports/' . $filename;
            Storage::disk('public')->put($pdfPath, $pdfContent);

            // Update report with file path
            $report->update([
                'report_file' => $pdfPath,
                'generated_at' => now(),
            ]);

            // Get the full URL for the stored PDF
            $pdfUrl = asset('storage/' . $pdfPath);

            return response()->json([
                'data' => [
                    'report' => $report->fresh(),
                    'pdf_url' => $pdfUrl,
                    'pdf_path' => $pdfPath,
                ],
                'status' => 200,
                'message' => 'PDF stored successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Error storing PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert cluster scores into percentage insights
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
     * Build radar chart data for SVG rendering
     */
    private function buildRadarChartData(array $clusterInsights): ?array
    {
        $count = count($clusterInsights);

        if ($count === 0) {
            return null;
        }

        $width = 320;
        $height = 320;
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($centerX, $centerY) - 24;
        $levels = 4;

        $circles = [];
        for ($i = 1; $i <= $levels; $i++) {
            $circles[] = $radius * ($i / $levels);
        }

        $axes = [];
        $labels = [];
        $polygonPoints = [];

        foreach ($clusterInsights as $index => $cluster) {
            $angle = (2 * pi() * $index / $count) - (pi() / 2);
            $axisX = $centerX + $radius * cos($angle);
            $axisY = $centerY + $radius * sin($angle);

            $axes[] = [
                'x1' => round($centerX, 2),
                'y1' => round($centerY, 2),
                'x2' => round($axisX, 2),
                'y2' => round($axisY, 2),
            ];

            $valueRadius = $radius * ($cluster['percentage'] / 100);
            $valueX = $centerX + $valueRadius * cos($angle);
            $valueY = $centerY + $valueRadius * sin($angle);
            $polygonPoints[] = round($valueX, 2) . ',' . round($valueY, 2);

            $labelRadius = $radius + 18;
            $labelX = $centerX + $labelRadius * cos($angle);
            $labelY = $centerY + $labelRadius * sin($angle);

            $labels[] = [
                'text' => $cluster['name'],
                'x' => round($labelX, 2),
                'y' => round($labelY, 2),
                'anchor' => $labelX >= $centerX ? 'start' : 'end',
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'center_x' => round($centerX, 2),
            'center_y' => round($centerY, 2),
            'radius' => $radius,
            'circles' => $circles,
            'axes' => $axes,
            'labels' => $labels,
            'polygon_points' => implode(' ', $polygonPoints),
        ];
    }
}

