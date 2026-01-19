<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Models\TestReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    /**
     * Get report for a test result
     * Returns the report data (if exists) or creates a new one
     */
    public function getReport($testResultId)
    {
        $testResult = TestResult::with([
            'user',
            'test.clusters.constructs',
            'report'
        ])->find($testResultId);

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

        $clusterInsights = $this->calculateClusterInsights($testResult->cluster_scores ?? []);
        $radarChart = $this->buildRadarChartData($clusterInsights);
        $clusterDetails = $this->buildClusterDetails($testResult);
        $constructDetails = $this->buildConstructDetails($clusterDetails);

        // Enrich cluster_scores and construct_scores with descriptions and behaviours
        $enrichedClusterScores = $this->enrichClusterScores($testResult);
        $enrichedConstructScores = $this->enrichConstructScores($testResult);

        // Get test result as array and update with enriched scores
        $testResultData = $testResult->toArray();
        $testResultData['cluster_scores'] = $enrichedClusterScores;
        $testResultData['construct_scores'] = $enrichedConstructScores;
        // Remove test relationship from test_result
        unset($testResultData['test']);

        // Convert report to array and remove test_result relationship to avoid duplication
        $reportData = $report->toArray();
        unset($reportData['test_result']);

        return response()->json([
            'data' => [
                'report' => $reportData,
                'test_result' => $testResultData,
                'cluster_insights' => $clusterInsights,
                'radar_chart' => $radarChart,
                'cluster_details' => $clusterDetails,
                'construct_details' => $constructDetails,
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
        $testResult = TestResult::with([
            'user',
            'test.clusters.constructs',
            'report'
        ])->find($testResultId);

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
        $clusterDetails = $this->buildClusterDetails($testResult);
        $constructDetails = $this->buildConstructDetails($clusterDetails);

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
            'clusterDetails' => $clusterDetails,
            'constructDetails' => $constructDetails,
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
        $testResult = TestResult::with([
            'user',
            'test.clusters.constructs',
            'report'
        ])->find($testResultId);

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
        $clusterDetails = $this->buildClusterDetails($testResult);
        $constructDetails = $this->buildConstructDetails($clusterDetails);

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
            'clusterDetails' => $clusterDetails,
            'constructDetails' => $constructDetails,
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
     * Generate and download short PDF report (clusters and constructs with bands only)
     */
    public function downloadShortPdf($testResultId)
    {
        $testResult = TestResult::with([
            'user',
            'test.clusters.constructs',
        ])->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Test result not found',
            ], 404);
        }

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

        // Generate PDF using container binding
        try {
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadView('reports.short-report', $data);
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
        $filename = 'short-report-' . $testResult->id . '-' . now()->format('Y-m-d') . '.pdf';

        // Return PDF download response
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Update report content (summary, recommendations, etc.)
     */
    public function updateReportContent(Request $request, $testResultId)
    {
        $testResult = TestResult::with('report')->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Test result not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'report_summary' => 'sometimes|nullable|string',
            'recommendations' => 'sometimes|nullable|string',
            'application_matrix' => 'sometimes|nullable|array',
            'radar_data' => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $report = $testResult->report;

        if (!$report) {
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
            ]);
        }

        $report->fill([
            'report_summary' => $request->input('report_summary', $report->report_summary),
            'recommendations' => $request->input('recommendations', $report->recommendations),
            'application_matrix' => $request->input('application_matrix', $report->application_matrix),
            'radar_data' => $request->input('radar_data', $report->radar_data),
            'generated_at' => now(),
        ]);

        $report->save();

        return response()->json([
            'data' => [
                'report' => $report->fresh(),
            ],
            'status' => 200,
            'message' => 'Report content updated successfully',
        ], 200);
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

    /**
     * Build descriptive cluster list with behaviours and constructs
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
                'high_behaviour' => $cluster->high_behaviour ?? $cluster->high_behavior ?? null,
                'medium_behaviour' => $cluster->medium_behaviour ?? $cluster->medium_behavior ?? null,
                'low_behaviour' => $cluster->low_behaviour ?? $cluster->low_behavior ?? null,
                'constructs' => $cluster->constructs
                    ? $cluster->constructs->map(function ($construct) {
                        return [
                            'id' => $construct->id,
                            'name' => $construct->name,
                            'description' => $construct->description ?? $construct->definition,
                            'high_behavior' => $construct->high_behavior,
                            'medium_behavior' => $construct->medium_behavior,
                            'low_behavior' => $construct->low_behavior,
                        ];
                    })->values()->all()
                    : [],
            ];
        })->values()->all();
    }

    /**
     * Flatten constructs list for easy consumption
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

    /**
     * Enrich cluster scores with description and behaviour based on category
     */
    private function enrichClusterScores(TestResult $testResult): array
    {
        $clusterScores = $testResult->cluster_scores ?? [];
        
        if (empty($clusterScores) || !is_array($clusterScores)) {
            return [];
        }

        if (!$testResult->test) {
            return $clusterScores;
        }

        // Build a lookup map of cluster details by name
        $clusterDetailsMap = [];
        $clusters = $testResult->test->clusters ?? collect();
        
        foreach ($clusters as $cluster) {
            $clusterDetailsMap[$cluster->name] = [
                'description' => $cluster->description,
                'area' => $cluster->area ?? null,
                'high_behaviour' => $cluster->high_behaviour ?? $cluster->high_behavior ?? null,
                'medium_behaviour' => $cluster->medium_behaviour ?? $cluster->medium_behavior ?? null,
                'low_behaviour' => $cluster->low_behaviour ?? $cluster->low_behavior ?? null,
            ];
        }

        // Enrich each cluster score
        $enriched = [];
        foreach ($clusterScores as $clusterName => $scoreData) {
            $clusterInfo = $clusterDetailsMap[$clusterName] ?? null;
            
            if (is_array($scoreData)) {
                $category = strtolower($scoreData['category'] ?? '');
                $behaviour = null;
                
                // Get the appropriate behaviour based on category
                if ($clusterInfo) {
                    switch ($category) {
                        case 'high':
                            $behaviour = $clusterInfo['high_behaviour'];
                            break;
                        case 'medium':
                            $behaviour = $clusterInfo['medium_behaviour'];
                            break;
                        case 'low':
                            $behaviour = $clusterInfo['low_behaviour'];
                            break;
                    }
                }

                $enriched[$clusterName] = array_merge($scoreData, [
                    'description' => $clusterInfo['description'] ?? null,
                    'area' => $clusterInfo['area'] ?? ($scoreData['area'] ?? null),
                    'behaviour' => $behaviour,
                ]);
            } else {
                // If score is not an array, keep it as is but add description if available
                $enriched[$clusterName] = $scoreData;
                if ($clusterInfo) {
                    $enriched[$clusterName] = [
                        'value' => $scoreData,
                        'description' => $clusterInfo['description'] ?? null,
                    ];
                }
            }
        }

        return $enriched;
    }

    /**
     * Enrich construct scores with description and behaviour based on category
     */
    private function enrichConstructScores(TestResult $testResult): array
    {
        $constructScores = $testResult->construct_scores ?? [];
        
        if (empty($constructScores) || !is_array($constructScores)) {
            return [];
        }

        if (!$testResult->test) {
            return $constructScores;
        }

        // Build a lookup map of construct details by name
        $constructDetailsMap = [];
        $clusters = $testResult->test->clusters ?? collect();
        
        foreach ($clusters as $cluster) {
            $constructs = $cluster->constructs ?? collect();
            foreach ($constructs as $construct) {
                $constructDetailsMap[$construct->name] = [
                    'description' => $construct->description ?? $construct->definition,
                    'high_behavior' => $construct->high_behavior,
                    'medium_behavior' => $construct->medium_behavior,
                    'low_behavior' => $construct->low_behavior,
                    'cluster_name' => $cluster->name,
                ];
            }
        }

        // Enrich each construct score
        $enriched = [];
        foreach ($constructScores as $constructName => $scoreData) {
            $constructInfo = $constructDetailsMap[$constructName] ?? null;
            
            if (is_array($scoreData)) {
                $category = strtolower($scoreData['category'] ?? '');
                $behaviour = null;
                
                // Get the appropriate behaviour based on category
                if ($constructInfo) {
                    switch ($category) {
                        case 'high':
                            $behaviour = $constructInfo['high_behavior'];
                            break;
                        case 'medium':
                            $behaviour = $constructInfo['medium_behavior'];
                            break;
                        case 'low':
                            $behaviour = $constructInfo['low_behavior'];
                            break;
                    }
                }

                $enriched[$constructName] = array_merge($scoreData, [
                    'description' => $constructInfo['description'] ?? null,
                    'behaviour' => $behaviour,
                    'cluster_name' => $constructInfo['cluster_name'] ?? null,
                ]);
            } else {
                // If score is not an array, keep it as is but add description if available
                $enriched[$constructName] = $scoreData;
                if ($constructInfo) {
                    $enriched[$constructName] = [
                        'value' => $scoreData,
                        'description' => $constructInfo['description'] ?? null,
                        'cluster_name' => $constructInfo['cluster_name'] ?? null,
                    ];
                }
            }
        }

        return $enriched;
    }

    /**
     * Generate and download PDF report using Snappy (wkhtmltopdf)
     * Based on test result ID and age group
     * 
     * @param int $testResultId
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadSnappyPdf($testResultId, Request $request)
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

        /* -----------------------------------------
        BUILD DATA
        ----------------------------------------- */

        $clusterScores   = $this->enrichClusterScores($testResult);
        $constructScores = $this->enrichConstructScores($testResult);

        // Sort by priority: High > Medium > Low
        $clusterScores = $this->sortByPriority($clusterScores);
        $constructScores = $this->sortByPriority($constructScores);

        // Get logo as base64
        $logoBase64 = $this->getLogoBase64();

        // Get user name for filename
        $userName = $testResult->user->name ?? 
                   trim(($testResult->user->first_name ?? '') . ' ' . ($testResult->user->last_name ?? '')) ?? 
                   'user';

        $data = [
            'user'                       => $testResult->user,
            'testResult'                 => $testResult,
            'test'                        => $testResult->test,
            'report'                      => $report,
            'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
            'generatedAt'                => ($report->generated_at ?? now())->format('F d, Y'),
            'clusterScores'              => $clusterScores,
            'constructScores'            => $constructScores,
            'reportSummary'              => $report->report_summary ?? null,
            'logoBase64'                 => $logoBase64,
            'radarClusterChartBase64'    => $this->generateRadarChartBase64($clusterScores, 'cluster'),
            'radarConstructChartBase64'  => $this->generateRadarChartBase64($constructScores, 'construct'),
        ];

        /* -----------------------------------------
        GENERATE PDF
        ----------------------------------------- */

        try {
            $pdf = \Barryvdh\Snappy\Facades\SnappyPdf::loadView(
                'reports.snappy-report',
                $data
            )
            ->setPaper('a4')
            ->setOrientation('portrait')
            ->setOption('encoding', 'UTF-8')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', '22mm')
            ->setOption('margin-bottom', '22mm')
            ->setOption('margin-left', '15mm')
            ->setOption('margin-right', '15mm');

            /* ---------- HEADER (EVERY PAGE EXCEPT FIRST) ---------- */
            $headerHtml = '';
            if (!empty($logoBase64)) {
                $headerHtml = '
                <div style="
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                    padding: 15px 0;
                    border-bottom: 2px solid #e9ecef;
                    text-align: center;
                    width: 100%;
                ">
                   
                </div>';
            }

            if (!empty($headerHtml)) {
                $pdf->setOption('header-html', $headerHtml);
                $pdf->setOption('header-spacing', 5);
            }

            /* ---------- FOOTER (EVERY PAGE) ---------- */
            $footerHtml = '
            <div style="
                font-size:8pt;
                color:#718096;
                padding:8px 15px;
                border-top:1px solid #e2e8f0;
                line-height:1.4;
                font-family: Arial, Helvetica, sans-serif;
            ">
                <div style="margin-top:4px;font-size:7pt;color:#a0aec0;text-align:center;">
                Disclaimer<br>
                   You have consented and taken this assessment for personal development purposes only. You understand results are not diagnostic, medical, or clinical, and represent self reported tendencies. These results may
be influenced by context, mood, and self perception. Use them as a starting point for reflection and coaching, not as a definitive judgment. For mental health or medical concerns, consult a qualified professional.
                   For any queries regarding the report, please send an email to: <strong>guide@axiscompass.in</strong>
                </div>
            </div>';

            $pdf->setOption('footer-html', $footerHtml);
            $pdf->setOption('footer-spacing', 5);

            // Create filename with user name
            $userName = $testResult->user->name ?? 
                       trim(($testResult->user->first_name ?? '') . ' ' . ($testResult->user->last_name ?? '')) ?? 
                       'user';
            $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);
            $filename = 'strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '.pdf';
            $output   = $pdf->output();

            Storage::disk('public')->put('reports/' . $filename, $output);

            $report->update([
                'report_file' => 'reports/' . $filename,
                'generated_at' => now(),
            ]);

            return response($output, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');

        } catch (\Exception $e) {
            \Log::error('Snappy PDF error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'PDF generation failed'
            ], 500);
        }
    }


    /**
     * Generate radar chart as base64 image
     * Uses QuickChart.io to generate radar charts
     *
     * @param array $scores
     * @param string $type 'cluster' or 'construct'
     * @return string|null Base64 encoded image
     */
    private function generateRadarChartBase64(array $scores, string $type = 'cluster')
    {
        if (empty($scores)) {
            return null;
        }

        // Prepare labels & data
        $labels = [];
        $values = [];

        foreach ($scores as $name => $data) {
            $labels[] = $name;
            $values[] = is_array($data)
                ? ($data['percentage'] ?? 0)
                : (float) $data;
        }

        $chartConfig = [
            'type' => 'radar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $type === 'cluster'
                        ? 'Cluster Strengths'
                        : 'Construct Strengths',
                    'data' => $values,
                    'backgroundColor' => 'rgba(102,126,234,0.2)',
                    'borderColor' => 'rgba(102,126,234,1)',
                    'borderWidth' => 2,
                    'pointBackgroundColor' => 'rgba(102,126,234,1)',
                    'pointBorderColor' => '#ffffff',
                    'pointHoverBackgroundColor' => 'rgba(102,126,234,1)',
                    'pointHoverBorderColor' => '#ffffff',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ]]
            ],
            'options' => [
                'responsive' => false,
                'maintainAspectRatio' => true,
                'aspectRatio' => 1,
                'layout' => [
                    'padding' => 20
                ],
                'scales' => [
                    'r' => [
                        'beginAtZero' => true,
                        'max' => 100,
                        'min' => 0,
                        'ticks' => [
                            'stepSize' => 20,
                            'font' => [
                                'size' => 10
                            ],
                            'color' => '#666',
                            'backdropColor' => 'transparent'
                        ],
                        'grid' => [
                            'color' => 'rgba(0,0,0,0.1)',
                            'lineWidth' => 1,
                            'circular' => true
                        ],
                        'pointLabels' => [
                            'font' => [
                                'size' => 11
                            ],
                            'color' => '#333',
                            'padding' => 10
                        ],
                        'angleLines' => [
                            'color' => 'rgba(0,0,0,0.1)',
                            'lineWidth' => 1,
                            'display' => true
                        ]
                    ]
                ],
                'plugins' => [
                    'legend' => [
                        'display' => false
                    ]
                ]
            ]
        ];

        // Generate image using QuickChart
        $response = Http::get('https://quickchart.io/chart', [
            'c' => json_encode($chartConfig),
            'width' => 600,
            'height' => 600,
            'format' => 'png',
            'backgroundColor' => 'white',
        ]);

        if (!$response->successful()) {
            return null;
        }

        return base64_encode($response->body());
    }

    /**
     * Get logo as base64 encoded string
     * 
     * @return string|null Base64 encoded logo image
     */
    private function getLogoBase64()
    {
        try {
            // Try to get logo from URL
            $logoUrl = 'https://assessments.axiscompass.co/assets/Logo-WBcOyAhZ.png';
            $response = Http::get($logoUrl);
            
            if ($response->successful()) {
                return base64_encode($response->body());
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch logo: ' . $e->getMessage());
        }

        // Fallback: try local file
        $localPaths = [
            public_path('images/logo.png'),
            public_path('assets/logo.png'),
            storage_path('app/public/logo.png'),
        ];

        foreach ($localPaths as $path) {
            if (file_exists($path)) {
                $imageData = file_get_contents($path);
                return base64_encode($imageData);
            }
        }

        return null;
    }

    /**
     * Sort scores by priority: High > Medium > Low
     * 
     * @param array $scores
     * @return array Sorted scores
     */
    private function sortByPriority(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }

        // Define priority order
        $priorityOrder = [
            'high' => 1,
            'medium' => 2,
            'low' => 3,
        ];

        uasort($scores, function ($a, $b) use ($priorityOrder) {
            $categoryA = strtolower($a['category'] ?? 'low');
            $categoryB = strtolower($b['category'] ?? 'low');
            
            $priorityA = $priorityOrder[$categoryA] ?? 99;
            $priorityB = $priorityOrder[$categoryB] ?? 99;
            
            // First sort by priority
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }
            
            // If same priority, maintain original order (or sort by name)
            return 0;
        });

        return $scores;
    }

    /**
     * Generate PDF report and send via email to user
     * 
     * @param int $testResultId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendPdfByEmail($testResultId, Request $request)
    {
        $testResult = TestResult::with(['user', 'test', 'report'])->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'data' => [],
                'status' => 404,
                'message' => 'Test result not found',
            ], 404);
        }

        if (!$testResult->user || !$testResult->user->email) {
            return response()->json([
                'data' => [],
                'status' => 400,
                'message' => 'User email not found',
            ], 400);
        }

        // Get or create report
        $report = $testResult->report;
        if (!$report) {
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'generated_at' => now(),
            ]);
        }

        /* -----------------------------------------
        BUILD DATA
        ----------------------------------------- */

        $clusterScores   = $this->enrichClusterScores($testResult);
        $constructScores = $this->enrichConstructScores($testResult);

        // Sort by priority: High > Medium > Low
        $clusterScores = $this->sortByPriority($clusterScores);
        $constructScores = $this->sortByPriority($constructScores);

        // Get logo as base64
        $logoBase64 = $this->getLogoBase64();

        // Get user name for filename
        $userName = $testResult->user->name ?? 
                   trim(($testResult->user->first_name ?? '') . ' ' . ($testResult->user->last_name ?? '')) ?? 
                   'user';

        $data = [
            'user'                       => $testResult->user,
            'testResult'                 => $testResult,
            'test'                        => $testResult->test,
            'report'                      => $report,
            'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
            'generatedAt'                => ($report->generated_at ?? now())->format('F d, Y'),
            'clusterScores'              => $clusterScores,
            'constructScores'            => $constructScores,
            'reportSummary'              => $report->report_summary ?? null,
            'logoBase64'                 => $logoBase64,
            'radarClusterChartBase64'    => $this->generateRadarChartBase64($clusterScores, 'cluster'),
            'radarConstructChartBase64'  => $this->generateRadarChartBase64($constructScores, 'construct'),
        ];

        /* -----------------------------------------
        GENERATE PDF
        ----------------------------------------- */

        try {
            $pdf = \Barryvdh\Snappy\Facades\SnappyPdf::loadView(
                'reports.snappy-report',
                $data
            )
            ->setPaper('a4')
            ->setOrientation('portrait')
            ->setOption('encoding', 'UTF-8')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', '22mm')
            ->setOption('margin-bottom', '22mm')
            ->setOption('margin-left', '15mm')
            ->setOption('margin-right', '15mm');

            /* ---------- HEADER (EVERY PAGE EXCEPT FIRST) ---------- */
            $headerHtml = '';
            if (!empty($logoBase64)) {
                $headerHtml = '
                <div style="
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                    padding: 15px 0;
                    border-bottom: 2px solid #e9ecef;
                    text-align: center;
                    width: 100%;
                ">
                    <img src="data:image/png;base64,' . $logoBase64 . '" alt="Strengths Compass Logo" style="max-width: 160px; height: auto; display: inline-block;" />
                </div>';
            }

            if (!empty($headerHtml)) {
                $pdf->setOption('header-html', $headerHtml);
                $pdf->setOption('header-spacing', 5);
            }

            /* ---------- FOOTER (EVERY PAGE) ---------- */
            $footerHtml = '
            <div style="
                font-size:8pt;
                color:#718096;
                padding:8px 15px;
                border-top:1px solid #e2e8f0;
                line-height:1.4;
                font-family: Arial, Helvetica, sans-serif;
            ">
                <div style="margin-top:4px;font-size:7pt;color:#a0aec0;text-align:center;">
                Disclaimer<br>
                   You have consented and taken this assessment for personal development purposes only. You understand results are not diagnostic, medical, or clinical, and represent self reported tendencies. These results may
be influenced by context, mood, and self perception. Use them as a starting point for reflection and coaching, not as a definitive judgment. For mental health or medical concerns, consult a qualified professional.
                   For any queries regarding the report, please send an email to: <strong>guide@axiscompass.in</strong>
                </div>
            </div>';

            $pdf->setOption('footer-html', $footerHtml);
            $pdf->setOption('footer-spacing', 5);

            // Create filename with user name
            $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);
            $filename = 'strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '.pdf';
            $output   = $pdf->output();

            // Save PDF to storage
            Storage::disk('public')->put('reports/' . $filename, $output);

            $report->update([
                'report_file' => 'reports/' . $filename,
                'generated_at' => now(),
            ]);

            /* -----------------------------------------
            SEND EMAIL WITH PDF ATTACHMENT
            ----------------------------------------- */

            $userEmail = $testResult->user->email;
            $userDisplayName = $userName;
            $testName = $testResult->test->title ?? 'Strengths Assessment';

            Mail::send([], [], function ($message) use ($userEmail, $userDisplayName, $output, $filename, $testName) {
                $message->to($userEmail, $userDisplayName)
                    ->subject('Your Strengths Compass Assessment Report - ' . $testName)
                    ->attachData($output, $filename, [
                        'mime' => 'application/pdf',
                    ])
                    ->html('
                        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;">
                                <h1 style="margin: 0; font-size: 24px;">Axis Strengths Compass</h1>
                                <p style="margin: 10px 0 0 0; opacity: 0.9;">Assessment Report</p>
                            </div>
                            <div style="padding: 30px; background: #ffffff;">
                                <h2 style="color: #667eea; margin-top: 0;">Your Assessment Report is Ready</h2>
                                <p>Dear ' . htmlspecialchars($userDisplayName) . ',</p>
                                <p>Thank you for completing the Strengths Compass Assessment. Your personalized report is attached to this email.</p>
                                <p>The report contains detailed insights about your strengths across six clusters and 18 psychological constructs, along with radar charts and personalized recommendations.</p>
                                <p><strong>Please review the attached PDF report for your complete assessment results.</strong></p>
                                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                    <p style="margin: 0; color: #555; font-size: 14px;">
                                        <strong>What\'s included in your report:</strong>
                                    </p>
                                    <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #555;">
                                        <li>Cluster-level analysis with priority bands</li>
                                        <li>Construct-level detailed insights</li>
                                        <li>Visual radar charts</li>
                                        <li>Personalized tendencies and recommendations</li>
                                    </ul>
                                </div>
                                <p>If you have any questions or need further clarification, please feel free to contact us at <strong>guide@axiscompass.in</strong>.</p>
                                <p style="margin-top: 30px;">
                                    Best regards,<br>
                                    <strong>Axis Strengths Compass Team</strong>
                                </p>
                            </div>
                            <div style="background: #f8f9fa; padding: 20px; text-align: center; color: #777; font-size: 12px; border-top: 1px solid #e9ecef;">
                                <p style="margin: 0;">This is an automated email. Please do not reply to this message.</p>
                            </div>
                        </div>
                    ');
            });

            return response()->json([
                'data' => [
                    'message' => 'PDF report sent successfully to ' . $userEmail,
                    'email' => $userEmail,
                    'filename' => $filename,
                ],
                'status' => 200,
                'message' => 'Report sent successfully via email',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('PDF Email error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'test_result_id' => $testResultId,
            ]);

            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Failed to send PDF via email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate PDF reports and send via email to multiple users (bulk)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendBulkPdfByEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|integer|exists:users,id',
            'test_result_id' => 'nullable|integer|exists:test_results,id', // Optional: specific test result for all users
            'send_latest_only' => 'nullable|boolean', // If true, send only latest test result per user
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userIds = $request->input('user_ids');
        $specificTestResultId = $request->input('test_result_id');
        $sendLatestOnly = $request->input('send_latest_only', true);

        $results = [
            'success' => [],
            'failed' => [],
            'total' => 0,
            'success_count' => 0,
            'failed_count' => 0,
        ];

        foreach ($userIds as $userId) {
            try {
                // If specific test result ID is provided, use it for all users
                if ($specificTestResultId) {
                    $testResult = TestResult::with(['user', 'test', 'report'])
                        ->where('id', $specificTestResultId)
                        ->where('user_id', $userId)
                        ->first();

                    if ($testResult) {
                        $emailResult = $this->sendPdfEmailForTestResult($testResult);
                        if ($emailResult['success']) {
                            $results['success'][] = [
                                'user_id' => $userId,
                                'user_email' => $testResult->user->email ?? null,
                                'test_result_id' => $testResult->id,
                                'message' => $emailResult['message'],
                            ];
                            $results['success_count']++;
                        } else {
                            $results['failed'][] = [
                                'user_id' => $userId,
                                'user_email' => $testResult->user->email ?? null,
                                'test_result_id' => $testResult->id,
                                'error' => $emailResult['message'],
                            ];
                            $results['failed_count']++;
                        }
                        $results['total']++;
                    } else {
                        $results['failed'][] = [
                            'user_id' => $userId,
                            'error' => 'Test result not found for this user',
                        ];
                        $results['failed_count']++;
                        $results['total']++;
                    }
                } else {
                    // Get test results for this user
                    $query = TestResult::with(['user', 'test', 'report'])
                        ->where('user_id', $userId);

                    if ($sendLatestOnly) {
                        $testResults = $query->orderBy('created_at', 'desc')->limit(1)->get();
                    } else {
                        $testResults = $query->orderBy('created_at', 'desc')->get();
                    }

                    if ($testResults->isEmpty()) {
                        $results['failed'][] = [
                            'user_id' => $userId,
                            'error' => 'No test results found for this user',
                        ];
                        $results['failed_count']++;
                        $results['total']++;
                        continue;
                    }

                    // Send email for each test result
                    foreach ($testResults as $testResult) {
                        $emailResult = $this->sendPdfEmailForTestResult($testResult);
                        if ($emailResult['success']) {
                            $results['success'][] = [
                                'user_id' => $userId,
                                'user_email' => $testResult->user->email ?? null,
                                'test_result_id' => $testResult->id,
                                'message' => $emailResult['message'],
                            ];
                            $results['success_count']++;
                        } else {
                            $results['failed'][] = [
                                'user_id' => $userId,
                                'user_email' => $testResult->user->email ?? null,
                                'test_result_id' => $testResult->id,
                                'error' => $emailResult['message'],
                            ];
                            $results['failed_count']++;
                        }
                        $results['total']++;
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Bulk PDF Email error for user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $results['failed'][] = [
                    'user_id' => $userId,
                    'error' => 'Failed to process: ' . $e->getMessage(),
                ];
                $results['failed_count']++;
                $results['total']++;
            }
        }

        return response()->json([
            'data' => $results,
            'status' => 200,
            'message' => sprintf(
                'Bulk email processing completed. Success: %d, Failed: %d, Total: %d',
                $results['success_count'],
                $results['failed_count'],
                $results['total']
            ),
        ], 200);
    }

    /**
     * Helper method to send PDF email for a specific test result
     * 
     * @param TestResult $testResult
     * @return array
     */
    private function sendPdfEmailForTestResult($testResult)
    {
        try {
            if (!$testResult->user || !$testResult->user->email) {
                return [
                    'success' => false,
                    'message' => 'User email not found',
                ];
            }

            // Get or create report
            $report = $testResult->report;
            if (!$report) {
                $report = TestReport::create([
                    'test_result_id' => $testResult->id,
                    'generated_at' => now(),
                ]);
            }

            /* -----------------------------------------
            BUILD DATA
            ----------------------------------------- */

            $clusterScores   = $this->enrichClusterScores($testResult);
            $constructScores = $this->enrichConstructScores($testResult);

            // Sort by priority: High > Medium > Low
            $clusterScores = $this->sortByPriority($clusterScores);
            $constructScores = $this->sortByPriority($constructScores);

            // Get logo as base64
            $logoBase64 = $this->getLogoBase64();

            // Get user name for filename
            $userName = $testResult->user->name ?? 
                       trim(($testResult->user->first_name ?? '') . ' ' . ($testResult->user->last_name ?? '')) ?? 
                       'user';

            $data = [
                'user'                       => $testResult->user,
                'testResult'                 => $testResult,
                'test'                        => $testResult->test,
                'report'                      => $report,
                'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
                'generatedAt'                => ($report->generated_at ?? now())->format('F d, Y'),
                'clusterScores'              => $clusterScores,
                'constructScores'            => $constructScores,
                'reportSummary'              => $report->report_summary ?? null,
                'logoBase64'                 => $logoBase64,
                'radarClusterChartBase64'    => $this->generateRadarChartBase64($clusterScores, 'cluster'),
                'radarConstructChartBase64'  => $this->generateRadarChartBase64($constructScores, 'construct'),
            ];

            /* -----------------------------------------
            GENERATE PDF
            ----------------------------------------- */

            $pdf = \Barryvdh\Snappy\Facades\SnappyPdf::loadView(
                'reports.snappy-report',
                $data
            )
            ->setPaper('a4')
            ->setOrientation('portrait')
            ->setOption('encoding', 'UTF-8')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', '22mm')
            ->setOption('margin-bottom', '22mm')
            ->setOption('margin-left', '15mm')
            ->setOption('margin-right', '15mm');

            /* ---------- HEADER (EVERY PAGE EXCEPT FIRST) ---------- */
            $headerHtml = '';
            if (!empty($logoBase64)) {
                $headerHtml = '
                <div style="
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                    padding: 15px 0;
                    border-bottom: 2px solid #e9ecef;
                    text-align: center;
                    width: 100%;
                ">
                    <img src="data:image/png;base64,' . $logoBase64 . '" alt="Strengths Compass Logo" style="max-width: 160px; height: auto; display: inline-block;" />
                </div>';
            }

            if (!empty($headerHtml)) {
                $pdf->setOption('header-html', $headerHtml);
                $pdf->setOption('header-spacing', 5);
            }

            /* ---------- FOOTER (EVERY PAGE) ---------- */
            $footerHtml = '
            <div style="
                font-size:8pt;
                color:#718096;
                padding:8px 15px;
                border-top:1px solid #e2e8f0;
                line-height:1.4;
                font-family: Arial, Helvetica, sans-serif;
            ">
                <div style="margin-top:4px;font-size:7pt;color:#a0aec0;text-align:center;">
                Disclaimer<br>
                   You have consented and taken this assessment for personal development purposes only. You understand results are not diagnostic, medical, or clinical, and represent self reported tendencies. These results may
be influenced by context, mood, and self perception. Use them as a starting point for reflection and coaching, not as a definitive judgment. For mental health or medical concerns, consult a qualified professional.
                   For any queries regarding the report, please send an email to: <strong>guide@axiscompass.in</strong>
                </div>
            </div>';

            $pdf->setOption('footer-html', $footerHtml);
            $pdf->setOption('footer-spacing', 5);

            // Create filename with user name
            $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);
            $filename = 'strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '.pdf';
            $output   = $pdf->output();

            // Save PDF to storage
            Storage::disk('public')->put('reports/' . $filename, $output);

            $report->update([
                'report_file' => 'reports/' . $filename,
                'generated_at' => now(),
            ]);

            /* -----------------------------------------
            SEND EMAIL WITH PDF ATTACHMENT
            ----------------------------------------- */

            $userEmail = $testResult->user->email;
            $userDisplayName = $userName;
            $testName = $testResult->test->title ?? 'Strengths Assessment';

            Mail::send([], [], function ($message) use ($userEmail, $userDisplayName, $output, $filename, $testName) {
                $message->to($userEmail, $userDisplayName)
                    ->subject('Your Strengths Compass Assessment Report - ' . $testName)
                    ->attachData($output, $filename, [
                        'mime' => 'application/pdf',
                    ])
                    ->html('
                        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;">
                                <h1 style="margin: 0; font-size: 24px;">Axis Strengths Compass</h1>
                                <p style="margin: 10px 0 0 0; opacity: 0.9;">Assessment Report</p>
                            </div>
                            <div style="padding: 30px; background: #ffffff;">
                                <h2 style="color: #667eea; margin-top: 0;">Your Assessment Report is Ready</h2>
                                <p>Dear ' . htmlspecialchars($userDisplayName) . ',</p>
                                <p>Thank you for completing the Strengths Compass Assessment. Your personalized report is attached to this email.</p>
                                <p>The report contains detailed insights about your strengths across six clusters and 18 psychological constructs, along with radar charts and personalized recommendations.</p>
                                <p><strong>Please review the attached PDF report for your complete assessment results.</strong></p>
                                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                    <p style="margin: 0; color: #555; font-size: 14px;">
                                        <strong>What\'s included in your report:</strong>
                                    </p>
                                    <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #555;">
                                        <li>Cluster-level analysis with priority bands</li>
                                        <li>Construct-level detailed insights</li>
                                        <li>Visual radar charts</li>
                                        <li>Personalized tendencies and recommendations</li>
                                    </ul>
                                </div>
                                <p>If you have any questions or need further clarification, please feel free to contact us at <strong>guide@axiscompass.in</strong>.</p>
                                <p style="margin-top: 30px;">
                                    Best regards,<br>
                                    <strong>Axis Strengths Compass Team</strong>
                                </p>
                            </div>
                            <div style="background: #f8f9fa; padding: 20px; text-align: center; color: #777; font-size: 12px; border-top: 1px solid #e9ecef;">
                                <p style="margin: 0;">This is an automated email. Please do not reply to this message.</p>
                            </div>
                        </div>
                    ');
            });

            return [
                'success' => true,
                'message' => 'PDF report sent successfully to ' . $userEmail,
            ];

        } catch (\Exception $e) {
            \Log::error('PDF Email error for test result', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send PDF via email: ' . $e->getMessage(),
            ];
        }
    }
}

