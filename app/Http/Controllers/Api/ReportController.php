<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Models\TestReport;
use App\Models\Test;
use App\Models\UserAnswer;
use App\Models\Construct;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

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
            'report',
            'answers.question'
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
            // Create a new report record with default summary
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            // Ensure report has a summary
            $this->ensureReportSummary($report, $testResult->user);
        }

        $clusterInsights = $this->calculateClusterInsights($testResult->cluster_scores ?? []);
        $radarChart = $this->buildRadarChartData($clusterInsights);
        $clusterDetails = $this->buildClusterDetails($testResult);
        $constructDetails = $this->buildConstructDetails($clusterDetails);

        // Enrich cluster_scores and construct_scores with descriptions and behaviours
        $enrichedClusterScores = $this->enrichClusterScores($testResult);
        $enrichedConstructScores = $this->enrichConstructScores($testResult);

        // Calculate SDB scores
        $sdbScores = $this->calculateSDBScores($testResult);

        // For CERC tests, combine SC Pro answers with CERC answers
        if ($testResult->test && $testResult->test->source === 'CERC') {
            $combinedAnswers = $this->getCombinedAnswersForReport($testResult);
            // Replace the answers collection with combined answers
            $testResult->setRelation('answers', $combinedAnswers);
        }

        // Get test result as array and update with enriched scores
        $testResultData = $testResult->toArray();
        $testResultData['cluster_scores'] = $enrichedClusterScores;
        $testResultData['construct_scores'] = $enrichedConstructScores;
        // Add SDB values to test_result
        $testResultData['sdb_flag'] = $testResult->sdb_flag;
        $testResultData['sdb_raw_score'] = $sdbScores['raw'];
        $testResultData['sdb_percentage'] = $sdbScores['percentage'];
        $testResultData['sdb_band'] = $sdbScores['band'];
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
            'test',
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
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            // Ensure report has a summary
            $this->ensureReportSummary($report, $testResult->user);
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

        // Calculate SDB scores
        $sdbScores = $this->calculateSDBScores($testResult);
        $sdbPercentage = $sdbScores['percentage'] ?? null;

        $radarClusterSvg = $this->generateRadarChartSvg($clusterScores);
        $radarConstructSvg = $this->generateRadarChartSvg($constructScores);

        $data = [
            'user'                       => $testResult->user,
            'testResult'                 => $testResult,
            'test'                        => $testResult->test,
            'report'                      => $report,
            'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
            'generatedAt'                => ($testResult->created_at ?? now())->format('F d, Y'),
            'clusterScores'              => $clusterScores,
            'constructScores'            => $constructScores,
            'reportSummary'              => $report->report_summary,
            'logoBase64'                 => $logoBase64,
            'radarClusterChartBase64'    => $radarClusterSvg,
            'radarConstructChartBase64'  => $radarConstructSvg,
            'sdbPercentage'              => $sdbPercentage,
            'isDompdf'                   => true, // Flag for dompdf-specific styling
        ];

        // Generate PDF using container binding (more reliable than facade)
        try {
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadView('reports.dompdf-report', $data);
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

        // Generate filename with user name
        $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);
        $filename = 'axis-strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '.pdf';

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
     * Generate and download PDF report using mPDF
     * 
     * @param int $testResultId
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function downloadMpdfPdf($testResultId)
    {
        $testResult = TestResult::with([
            'user',
            'test',
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
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            // Ensure report has a summary
            $this->ensureReportSummary($report, $testResult->user);
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

        // Calculate SDB scores
        $sdbScores = $this->calculateSDBScores($testResult);
        $sdbPercentage = $sdbScores['percentage'] ?? null;

        // For mPDF, use SVG optimized for mPDF rendering
        $radarClusterImage = $this->generateRadarmpdfChartSvg($clusterScores);
        $radarConstructImage = $this->generateRadarmpdfChartSvg($constructScores);

        // Strip embedded disclaimer from report summary (shown in page footer instead)
        $reportSummary = $this->stripDisclaimerFromSummary($report->report_summary ?? '');

        $data = [
            'user'                       => $testResult->user,
            'testResult'                 => $testResult,
            'test'                        => $testResult->test,
            'report'                      => $report,
            'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
            'generatedAt'                => ($testResult->created_at ?? now())->format('F d, Y'),
            'clusterScores'              => $clusterScores,
            'constructScores'            => $constructScores,
            'reportSummary'              => $reportSummary,
            'logoBase64'                 => $logoBase64,
            'radarClusterChartBase64'    => $radarClusterImage,
            'radarConstructChartBase64'  => $radarConstructImage,
            'sdbPercentage'              => $sdbPercentage,
        ];

        // Generate HTML content from view
        $html = view('reports.mpdf-report', $data)->render();

        // Generate PDF using mPDF
        try {
            // Set up mPDF configuration
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];
            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 12,
                'margin_bottom' => 18,
                'margin_header' => 8,
                'margin_footer' => 15,
                'tempDir' => storage_path('app/temp'),
            ]);

            // Set metadata
            $mpdf->SetTitle('Axis Strengths Compass Report');
            $mpdf->SetAuthor('Axis Strengths Compass');
            $mpdf->SetCreator('Axis Strengths Compass System');

            // Full report: Disclaimer in page footer (Report Summary pages only - controlled via htmlpagefooter in blade)
            // Write HTML content
            $mpdf->WriteHTML($html);

        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
                'error' => $e->getTraceAsString(),
            ], 500);
        }

        // Generate filename with user name and test source (SC Pro / CERC) for differentiation
        $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);
        $sourceSlug = $this->getReportSourceSlug($testResult);
        $filename = 'axis-strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '-' . $sourceSlug . '.pdf';

        // Get PDF output
        $pdfOutput = $mpdf->Output('', 'S');

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
     * Generate and download short PDF report using mPDF (Cover + Summary + Guidance only)
     * 
     * @param int $testResultId
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function downloadShortMpdfPdf($testResultId)
    {
        $testResult = TestResult::with([
            'user',
            'test',
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
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            // Ensure report has a summary
            $this->ensureReportSummary($report, $testResult->user);
        }

        /* -----------------------------------------
        BUILD DATA
        ----------------------------------------- */

        $clusterScores = $this->enrichClusterScores($testResult);

        // Sort by priority: High > Medium > Low
        $clusterScores = $this->sortByPriority($clusterScores);

        // Get logo as base64
        $logoBase64 = $this->getLogoBase64();

        // Get user name for filename
        $userName = $testResult->user->name ?? 
                   trim(($testResult->user->first_name ?? '') . ' ' . ($testResult->user->last_name ?? '')) ?? 
                   'user';

        // Calculate SDB scores
        $sdbScores = $this->calculateSDBScores($testResult);
        $sdbPercentage = $sdbScores['percentage'] ?? null;

        // Strip embedded disclaimer from report summary (shown in page footer instead)
        $reportSummary = $this->stripDisclaimerFromSummary($report->report_summary ?? '');

        $data = [
            'user'                       => $testResult->user,
            'testResult'                 => $testResult,
            'test'                        => $testResult->test,
            'report'                      => $report,
            'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
            'generatedAt'                => ($testResult->created_at ?? now())->format('F d, Y'),
            'clusterScores'              => $clusterScores,
            'reportSummary'              => $reportSummary,
            'logoBase64'                 => $logoBase64,
            'sdbPercentage'              => $sdbPercentage,
        ];

        // Generate HTML content from short report view
        $html = view('reports.mpdf-short-report', $data)->render();

        // Generate PDF using mPDF
        try {
            // Set up mPDF configuration
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];
            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 30,
                'margin_header' => 10,
                'margin_footer' => 15,
                'tempDir' => storage_path('app/temp'),
            ]);

            // Set metadata
            $mpdf->SetTitle('Axis Strengths Compass Report');
            $mpdf->SetAuthor('Axis Strengths Compass');
            $mpdf->SetCreator('Axis Strengths Compass System');

            // Short report: Disclaimer in page footer (Report Summary pages only - controlled via htmlpagefooter in blade)
            // Write HTML content
            $mpdf->WriteHTML($html);

        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
                'error' => $e->getTraceAsString(),
            ], 500);
        }

        // Generate filename with user name and test source (SC Pro / CERC) for differentiation
        $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);
        $sourceSlug = $this->getReportSourceSlug($testResult);
        $filename = 'axis-strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '-' . $sourceSlug . '.pdf';

        // Get PDF output
        $pdfOutput = $mpdf->Output('', 'S');

        // Return PDF download response with proper headers
        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($pdfOutput));
    }

    /**
     * Get a safe slug for test source to use in report filenames (e.g. SC-Pro, CERC).
     */
    protected function getReportSourceSlug(TestResult $testResult): string
    {
        $source = $testResult->test?->source ?? 'SC Pro';
        $slug = preg_replace('/[^a-zA-Z0-9-]/', '-', trim((string) $source));
        return $slug !== '' ? $slug : 'SC-Pro';
    }

    /**
     * Download multiple users' reports as a ZIP file.
     * Accepts user_ids (comma-separated or array) and type (full|short).
     * Optional: test_id to filter by specific test.
     */
    public function downloadBulkReportsAsZip(Request $request)
    {
        $userIdsInput = $request->input('user_ids');
        if (is_string($userIdsInput)) {
            $userIdsInput = array_filter(array_map('intval', explode(',', $userIdsInput)));
        } elseif (is_array($userIdsInput)) {
            $userIdsInput = array_filter(array_map('intval', $userIdsInput));
        } else {
            $userIdsInput = [];
        }

        $validator = Validator::make(array_merge($request->all(), ['user_ids' => $userIdsInput]), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'type' => 'nullable|in:full,short',
            'test_id' => 'nullable|exists:tests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $request->input('type', 'full');
        $testId = $request->input('test_id');

        $query = TestResult::with(['user', 'test', 'report'])
            ->whereIn('user_id', $userIdsInput)
            ->whereHas('user', function ($q) {
                $q->whereRaw('LOWER(role) = ?', ['user']);
            })
            ->orderByDesc('created_at');

        if ($testId) {
            $query->where('test_id', $testId);
        }

        $testResults = $query->get();

        if ($testResults->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No test results found for the selected users',
            ], 404);
        }

        $zip = new \ZipArchive();
        $zipFileName = 'reports-bulk-' . now()->format('Y-m-d_His') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create ZIP file',
            ], 500);
        }

        $addedCount = 0;
        foreach ($testResults as $testResult) {
            $result = $this->buildMpdfReportForZip($testResult, $type);
            if ($result) {
                $zip->addFromString($result['filename'], $result['pdf']);
                $addedCount++;
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($zipPath);
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate any reports',
            ], 500);
        }

        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Build mPDF report content for a single test result (for ZIP export).
     * Returns ['pdf' => string, 'filename' => string] or null on failure.
     */
    protected function buildMpdfReportForZip(TestResult $testResult, string $type): ?array
    {
        $testResult->load(['user', 'test', 'report']);

        $report = $testResult->report;
        if (!$report) {
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            $this->ensureReportSummary($report, $testResult->user);
        }

        $clusterScores = $this->enrichClusterScores($testResult);
        $clusterScores = $this->sortByPriority($clusterScores);
        $logoBase64 = $this->getLogoBase64();
        $userName = $testResult->user->name ?? trim(($testResult->user->first_name ?? '') . ' ' . ($testResult->user->last_name ?? '')) ?? 'user';
        $sdbScores = $this->calculateSDBScores($testResult);
        $sdbPercentage = $sdbScores['percentage'] ?? null;
        $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);

        if ($type === 'full') {
            $constructScores = $this->enrichConstructScores($testResult);
            $constructScores = $this->sortByPriority($constructScores);
            $radarClusterImage = $this->generateRadarmpdfChartSvg($clusterScores);
            $radarConstructImage = $this->generateRadarmpdfChartSvg($constructScores);

            $data = [
                'user' => $testResult->user,
                'testResult' => $testResult,
                'test' => $testResult->test,
                'report' => $report,
                'testName' => $testResult->test->title ?? 'Strengths Assessment',
                'generatedAt' => ($testResult->created_at ?? now())->format('F d, Y'),
                'clusterScores' => $clusterScores,
                'constructScores' => $constructScores,
                'reportSummary' => $report->report_summary,
                'logoBase64' => $logoBase64,
                'radarClusterChartBase64' => $radarClusterImage,
                'radarConstructChartBase64' => $radarConstructImage,
                'sdbPercentage' => $sdbPercentage,
            ];
            $html = view('reports.mpdf-report', $data)->render();
        } else {
            $data = [
                'user' => $testResult->user,
                'testResult' => $testResult,
                'test' => $testResult->test,
                'report' => $report,
                'testName' => $testResult->test->title ?? 'Strengths Assessment',
                'generatedAt' => ($testResult->created_at ?? now())->format('F d, Y'),
                'clusterScores' => $clusterScores,
                'reportSummary' => $report->report_summary,
                'logoBase64' => $logoBase64,
                'sdbPercentage' => $sdbPercentage,
            ];
            $html = view('reports.mpdf-short-report', $data)->render();
        }

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => $type === 'full' ? 12 : 15,
                'margin_bottom' => $type === 'full' ? 18 : 30,
                'margin_header' => $type === 'full' ? 8 : 10,
                'margin_footer' => $type === 'full' ? 10 : 15,
                'tempDir' => storage_path('app/temp'),
            ]);

            $mpdf->SetTitle('Axis Strengths Compass Report');
            $mpdf->SetAuthor('Axis Strengths Compass');
            $mpdf->SetCreator('Axis Strengths Compass System');

            if ($type === 'short') {
                $footerHtml = '
                <div style="font-size: 7pt; color: #6c757d; text-align: center; line-height: 1.2; padding: 8px 10px; border-top: 1px solid #e9ecef;">
                <p><b>Disclaimer:</b></p>
                You have consented and taken this assessment for personal development purposes only. You understand results are not diagnostic, medical, or clinical, and represent self reported
                tendencies. These results may be influenced by context, mood, and self perception. Use them as a starting point for reflection and coaching, not as a definitive judgment. For mental health
                or medical concerns, consult a qualified professional. For any queries regarding the report, please send an email to: <b>guide@axiscompass.in</b>
                </div>';
                $mpdf->SetHTMLFooter($footerHtml);
            }

            $mpdf->WriteHTML($html);
            $pdfOutput = $mpdf->Output('', 'S');
        } catch (\Exception $e) {
            \Log::error('Bulk report PDF generation failed', ['test_result_id' => $testResult->id, 'error' => $e->getMessage()]);
            return null;
        }

        $sourceSlug = $this->getReportSourceSlug($testResult);
        $suffix = $type === 'full' ? 'full' : 'short';
        $filename = 'report-' . $sanitizedUserName . '-' . $testResult->id . '-' . $sourceSlug . '-' . $suffix . '.pdf';

        return ['pdf' => $pdfOutput, 'filename' => $filename];
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
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            // Ensure report has a summary
            $this->ensureReportSummary($report, $testResult->user);
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
                $defaultSummary = $this->generateDefaultSummary($testResult->user);
                $report = TestReport::create([
                    'test_result_id' => $testResult->id,
                    'report_summary' => $defaultSummary,
                    'generated_at' => now(),
                ]);
            } else {
                // Ensure report has a summary
                $this->ensureReportSummary($report, $testResult->user);
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
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
            ]);
        } else {
            // Ensure report has a summary if not being updated
            if (!$request->has('report_summary') && empty($report->report_summary)) {
                $this->ensureReportSummary($report, $testResult->user);
            }
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
     * Determine strength category by percentage: 0-59 = Low, 60-75 = Medium, 76-100 = High
     */
    private function getStrengthCategory(int $percentage): string
    {
        if ($percentage >= 76) {
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
     * Build descriptive cluster list with behaviours and constructs.
     * Uses test_cluster_construct so constructs per cluster are test-specific.
     */
    private function buildClusterDetails(TestResult $testResult): array
    {
        if (!$testResult->test) {
            return [];
        }

        $test = $testResult->test;
        $clusters = $test->clusters ?? collect();

        if ($clusters->isEmpty()) {
            return [];
        }

        $pivot = DB::table('test_cluster_construct')
            ->where('test_id', $test->id)
            ->get()
            ->groupBy('cluster_id');

        return $clusters->map(function ($cluster) use ($pivot) {
            $constructIds = ($pivot->get($cluster->id) ?? collect())->pluck('construct_id')->unique()->all();
            $constructs = !empty($constructIds)
                ? Construct::whereIn('id', $constructIds)->get()
                : $cluster->constructs ?? collect(); // legacy: cluster->constructs

            return [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'description' => $cluster->description,
                'high_behaviour' => $cluster->high_behaviour ?? $cluster->high_behavior ?? null,
                'medium_behaviour' => $cluster->medium_behaviour ?? $cluster->medium_behavior ?? null,
                'low_behaviour' => $cluster->low_behaviour ?? $cluster->low_behavior ?? null,
                'constructs' => $constructs->map(function ($construct) {
                    return [
                        'id' => $construct->id,
                        'name' => $construct->name,
                        'description' => $construct->description ?? $construct->definition,
                        'high_behavior' => $construct->high_behavior,
                        'medium_behavior' => $construct->medium_behavior,
                        'low_behavior' => $construct->low_behavior,
                    ];
                })->values()->all(),
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
                // Recalculate category from average (always use current thresholds: 60, 76)
                $average = $scoreData['average'] ?? 0;
                $percentage = $this->convertScoreToPercentage($average);
                $category = strtolower($this->getStrengthCategory($percentage));
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
                    'category' => $category,
                    'description' => $clusterInfo['description'] ?? null,
                    'area' => $clusterInfo['area'] ?? ($scoreData['area'] ?? null),
                    'behaviour' => $behaviour,
                    'percentage' => $percentage,
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

        // Build a lookup map of construct details by name (test-specific cluster via test_cluster_construct)
        $constructDetailsMap = [];
        $test = $testResult->test;
        $pivotRows = DB::table('test_cluster_construct')
            ->where('test_id', $test->id)
            ->get();

        if ($pivotRows->isNotEmpty()) {
            $constructIds = $pivotRows->pluck('construct_id')->unique()->all();
            $clusterIds = $pivotRows->pluck('cluster_id')->unique()->all();
            $constructs = Construct::whereIn('id', $constructIds)->get()->keyBy('id');
            $clusters = $test->clusters->keyBy('id');
            foreach ($pivotRows as $row) {
                $construct = $constructs->get($row->construct_id);
                $cluster = $clusters->get($row->cluster_id);
                if ($construct && $cluster) {
                    $constructDetailsMap[$construct->name] = [
                        'description' => $construct->description ?? $construct->definition,
                        'high_behavior' => $construct->high_behavior,
                        'medium_behavior' => $construct->medium_behavior,
                        'low_behavior' => $construct->low_behavior,
                        'cluster_name' => $cluster->name,
                    ];
                }
            }
        } else {
            // Legacy: from test->clusters->constructs
            $clusters = $test->clusters ?? collect();
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
        }

        // Enrich each construct score (use stored percentage and category for exact consistency with result/Excel)
        $enriched = [];
        foreach ($constructScores as $constructName => $scoreData) {
            $constructInfo = $constructDetailsMap[$constructName] ?? null;

            if (is_array($scoreData)) {
                $average = $scoreData['average'] ?? 0;
                $percentage = isset($scoreData['percentage']) ? (float) $scoreData['percentage'] : $this->convertScoreToPercentage($average);
                $category = isset($scoreData['category']) ? strtolower($scoreData['category']) : strtolower($this->getStrengthCategory((int) round($percentage)));
                $behaviour = null;

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
                    'category' => $category,
                    'description' => $constructInfo['description'] ?? null,
                    'behaviour' => $behaviour,
                    'cluster_name' => $constructInfo['cluster_name'] ?? null,
                    'percentage' => $percentage,
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
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
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
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            // Ensure report has a summary
            $this->ensureReportSummary($report, $testResult->user);
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

        // Calculate SDB scores
        $sdbScores = $this->calculateSDBScores($testResult);
        $sdbPercentage = $sdbScores['percentage'] ?? null;

        $radarClusterSvg = $this->generateRadarChartSvg($clusterScores);
        $radarConstructSvg = $this->generateRadarChartSvg($constructScores);

        $data = [
            'user'                       => $testResult->user,
            'testResult'                 => $testResult,
            'test'                        => $testResult->test,
            'report'                      => $report,
            'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
            'generatedAt'                => ($testResult->created_at ?? now())->format('F d, Y'),
            'clusterScores'              => $clusterScores,
            'constructScores'            => $constructScores,
            'reportSummary'              => $report->report_summary,
            'logoBase64'                 => $logoBase64,
            'radarClusterChartBase64'    => $radarClusterSvg,
            'radarConstructChartBase64'  => $radarConstructSvg,
            
            'sdbPercentage'              => $sdbPercentage,
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
            $filename = 'axis-strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '.pdf';
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
    // private function generateRadarChartBase64(array $scores, string $type = 'cluster')
    // {
    //     if (empty($scores)) {
    //         return null;
    //     }

    //     // Prepare labels & data with scores
    //     $labels = [];
    //     $values = [];

    //     foreach ($scores as $name => $data) {
    //         $percentage = is_array($data)
    //             ? ($data['percentage'] ?? 0)
    //             : (float) $data;
            
    //         // Format label with score below name (using special formatting for styling)
    //         // Chart.js will render this, but we'll style it in the pointLabels config
    //         $labels[] = $name . "\n" . round($percentage) . "%";
    //         $values[] = $percentage;
    //     }

    //     $chartConfig = [
    //         'type' => 'radar',
    //         'data' => [
    //             'labels' => $labels,
    //             'datasets' => [[
    //                 'label' => $type === 'cluster'
    //                     ? 'Cluster Strengths'
    //                     : 'Construct Strengths',
    //                 'data' => $values,
    //                 'backgroundColor' => 'rgba(255, 152, 0, 0.25)', // Light orange fill
    //                 'borderColor' => 'rgba(255, 152, 0, 1)', // Orange border
    //                 'borderWidth' => 2.5,
    //                 'pointBackgroundColor' => 'rgba(255, 152, 0, 1)', // Orange points
    //                 'pointBorderColor' => '#ffffff',
    //                 'pointBorderWidth' => 2,
    //                 'pointHoverBackgroundColor' => 'rgba(255, 152, 0, 1)',
    //                 'pointHoverBorderColor' => '#ffffff',
    //                 'pointRadius' => 5,
    //                 'pointHoverRadius' => 7,
    //             ]]
    //         ],
    //         'options' => [
    //             'responsive' => false,
    //             'maintainAspectRatio' => true,
    //             'aspectRatio' => 1,
    //             'layout' => [
    //                 'padding' => [
    //                     'top' => 30,
    //                     'bottom' => 30,
    //                     'left' => 30,
    //                     'right' => 30
    //                 ]
    //             ],
    //             'scales' => [
    //                 'r' => [
    //                     'beginAtZero' => true,
    //                     'max' => 100,
    //                     'min' => 0,
    //                     'ticks' => [
    //                         'stepSize' => 20,
    //                         'count' => 6,
    //                         'maxTicksLimit' => 6,
    //                         'precision' => 0,
    //                         'font' => [
    //                             'size' => 11,
    //                             'family' => 'Arial, sans-serif'
    //                         ],
    //                         'color' => '#666666',
    //                         'backdropColor' => 'transparent',
    //                         'showLabelBackdrop' => false,
    //                         'z' => 1
    //                     ],
    //                     'grid' => [
    //                         'color' => 'rgba(0, 0, 0, 0.15)',
    //                         'lineWidth' => 1,
    //                         'circular' => true
    //                     ],
    //                     'pointLabels' => [
    //                         'font' => [
    //                             'size' => 12,
    //                             'family' => 'Arial, sans-serif',
    //                             'weight' => 'bold'
    //                         ],
    //                         'color' => '#333333',
    //                         'padding' => 15,
    //                         'usePointStyle' => false
    //                     ],
    //                     'angleLines' => [
    //                         'color' => 'rgba(0, 0, 0, 0.15)',
    //                         'lineWidth' => 1,
    //                         'display' => true,
    //                         'circular' => true
    //                     ]
    //                 ]
    //             ],
    //             'plugins' => [
    //                 'legend' => [
    //                     'display' => false
    //                 ],
    //                 'tooltip' => [
    //                     'enabled' => false
    //                 ]
    //             ]
    //         ]
    //     ];

    //     // Generate image using QuickChart
    //     $response = Http::get('https://quickchart.io/chart', [
    //         'c' => json_encode($chartConfig),
    //         'width' => 600,
    //         'height' => 600,
    //         'format' => 'png',
    //         'backgroundColor' => 'white',
    //     ]);

    //     if (!$response->successful()) {
    //         return null;
    //     }

    //     return base64_encode($response->body());
    // }

    // private function generateRadarChartBase64(array $scores, string $type = 'cluster')
    // {
    //     if (empty($scores)) {
    //         return null;
    //     }
    
    //     $labels = [];
    //     $values = [];
    
    //     foreach ($scores as $name => $data) {
    //         $percentage = is_array($data)
    //             ? ($data['percentage'] ?? 0)
    //             : (float) $data;
    
    //         $labels[] = $name;
    //         $values[] = round($percentage);
    //     }
    
    //     $chartConfig = [
    //         'type' => 'radar',
    //         'data' => [
    //             'labels' => $labels,
    //             'datasets' => [[
    //                 'label' => $type === 'cluster'
    //                     ? 'Cluster Strengths'
    //                     : 'Construct Strengths',
    //                 'data' => $values,
    //                 'backgroundColor' => 'rgba(255,152,0,0.18)',
    //                 'borderColor' => 'rgba(255,152,0,1)',
    //                 'borderWidth' => 3,
    //                 'pointBackgroundColor' => 'rgba(255,152,0,1)',
    //                 'pointBorderColor' => '#ffffff',
    //                 'pointBorderWidth' => 2,
    //                 'pointRadius' => 6,
    //                 'pointHoverRadius' => 8,
    //             ]]
    //         ],
    //         'options' => [
    //             'responsive' => false,
    //             'maintainAspectRatio' => true,
    //             'aspectRatio' => 1,
    //             'layout' => [
    //                 'padding' => 50
    //             ],
    //             'scales' => [
    //                 'r' => [
    //                     // 🔥 CRITICAL FIX
    //                     'min' => 0,
    //                     'max' => 100,
    //                     'bounds' => 'ticks', // ⭐ THIS STOPS AUTO-ZOOM
    
    //                     'grid' => [
    //                         'circular' => true,
    //                         'color' => 'rgba(0,0,0,0.12)',
    //                         'lineWidth' => 1.5
    //                     ],
    
    //                     'angleLines' => [
    //                         'color' => 'rgba(0,0,0,0.12)',
    //                         'lineWidth' => 1.5
    //                     ],
    
    //                     // 🔥 FORCE EXACT TICKS
    //                     'ticks' => [
    //                         'stepSize' => 20,
    //                         'autoSkip' => false,
    //                         'precision' => 0,
    //                         'showLabelBackdrop' => false,
    //                         'color' => '#6b7280',
    //                         'font' => [
    //                             'size' => 13,
    //                             'weight' => 'bold'
    //                         ],
    //                         'callback' => "function(value) { return value; }"
    //                     ],
    
    //                     'pointLabels' => [
    //                         'font' => [
    //                             'size' => 16,
    //                             'weight' => 'bold'
    //                         ],
    //                         'color' => '#0f172a',
    //                         'padding' => 25
    //                     ]
    //                 ]
    //             ],
    //             'plugins' => [
    //                 'legend' => [
    //                     'display' => false
    //                 ],
    //                 'tooltip' => [
    //                     'enabled' => false
    //                 ]
    //             ]
    //         ]
    //     ];
    
    //     $response = Http::get('https://quickchart.io/chart', [
    //         'c' => json_encode($chartConfig),
    //         'width' => 700,
    //         'height' => 700,
    //         'format' => 'png',
    //         'backgroundColor' => 'white',
    //     ]);
    
    //     if (!$response->successful()) {
    //         return null;
    //     }
    
    //     return base64_encode($response->body());
    // }


    private function generateRadarChartSvg(array $scores): string
    {
        /* ================================
           CONFIG (PDF SAFE)
           ================================ */
        $size        = 800;   // Bigger canvas to avoid clipping
        $center      = 400;   // Move chart inward
        $radius      = 210;   // Slightly smaller radar
        $labelRadius = $radius + 75;
        $levels      = [0, 20, 40, 60, 80, 100];
    
        /* ================================
           DATA PREP
           ================================ */
        $labels = array_keys($scores);
    
        $values = array_map(function ($v) {
            if (is_array($v)) {
                $v = $v['percentage'] ?? 0;
            }
            return (float) str_replace('%', '', (string) $v);
        }, array_values($scores));
    
        $count = count($labels);
        $angleStep = (float) ((2 * pi()) / max((int) $count, 1));
    
        /* ================================
           TEXT WRAP HELPER
           ================================ */
        $wrapLabel = function (string $text, int $maxChars = 18): array {
            return explode("\n", wordwrap($text, $maxChars, "\n", true));
        };
    
        /* ================================
           SVG START
           ================================ */
        $svg = [];
        $svg[] = "<svg width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}' xmlns='http://www.w3.org/2000/svg'>";
    
        /* ---------- GRID CIRCLES ---------- */
        foreach ($levels as $level) {
            $r = ($level / 100) * $radius;
            $svg[] = "<circle cx='{$center}' cy='{$center}' r='{$r}' fill='none' stroke='#e5e7eb' stroke-width='1'/>";
        }
    
        /* ---------- AXES + LABELS ---------- */
        foreach ($labels as $i => $label) {
            $value = $values[$i];
            $angle = (-pi() / 2) + ($i * $angleStep);
    
            // Axis line
            $x = $center + cos($angle) * $radius;
            $y = $center + sin($angle) * $radius;
            $svg[] = "<line x1='{$center}' y1='{$center}' x2='{$x}' y2='{$y}' stroke='#d1d5db'/>";
    
            // Label position
            $lx = $center + cos($angle) * $labelRadius;
            $ly = $center + sin($angle) * $labelRadius;
    
            // Smart alignment
            if (abs(cos($angle)) < 0.3) {
                $anchor = 'middle';
            } elseif (cos($angle) > 0) {
                $anchor = 'start';
            } else {
                $anchor = 'end';
            }
    
            // Wrap label text
            $lines = $wrapLabel($label, 18);
    
            $svg[] = "<text x='{$lx}' y='{$ly}' text-anchor='{$anchor}' font-family='Arial, Helvetica, sans-serif'>";
    
            $dy = 0;
            foreach ($lines as $line) {
                $svg[] = "<tspan x='{$lx}' dy='{$dy}' font-size='14' font-weight='700' fill='#0f172a'>{$line}</tspan>";
                $dy = 18;
            }
    
            // Percentage
            $svg[] = "<tspan x='{$lx}' dy='18' font-size='13' font-weight='600' fill='#64748b'>{$value}%</tspan>";
            $svg[] = "</text>";
        }
    
        /* ---------- SCORE POLYGON ---------- */
        $points = [];
        foreach ($values as $i => $value) {
            $angle = (-pi() / 2) + ($i * $angleStep);
            $r = ($value / 100) * $radius;
            $x = $center + cos($angle) * $r;
            $y = $center + sin($angle) * $r;
            $points[] = "{$x},{$y}";
        }
    
        $svg[] = "<polygon points='" . implode(' ', $points) . "' fill='rgba(255,152,0,0.22)' stroke='rgba(255,152,0,1)' stroke-width='3'/>";
    
        /* ---------- RADIAL TICK LABELS ---------- */
        foreach ($levels as $level) {
            $y = $center - ($level / 100) * $radius;
            $svg[] = "<text x='" . ($center + 12) . "' y='{$y}' font-size='12' fill='#6b7280'>{$level}</text>";
        }
    
        $svg[] = "</svg>";
    
        return implode('', $svg);
    }

    /**
     * Generate radar chart SVG optimized for mPDF
     * Creates SVG with proper formatting for mPDF rendering
     *
     * @param array $scores
     * @return string SVG string
     */
    private function generateRadarmpdfChartSvg(array $scores): string
    {
        if (empty($scores)) {
            return '';
        }
    
        /* ================================
           CONFIG (FOR MANY CONSTRUCTS)
           ================================ */
        $size        = 900;   // Bigger canvas
        $center      = 450;
        $radius      = 260;
        $labelRadius = $radius + 55;
    
        $levels = [0, 20, 40, 60, 80, 100];
    
        $labelFontSize = 10;
        $valueFontSize = 9;
    
        /* ================================
           DATA PREP
           ================================ */
        $labels = array_keys($scores);
    
        $values = array_map(function ($v) {
            if (is_array($v)) {
                $v = $v['percentage'] ?? 0;
            }
            return (float) $v;
        }, array_values($scores));
    
        $count = count($labels);
        $angleStep = (2 * pi()) / max($count, 1);
    
        /* ================================
           SVG START
           ================================ */
        $svg = [];
    
        // Start SVG with proper formatting for mPDF (no XML declaration when embedded in HTML)
        $svg[] = "<svg width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}' xmlns='http://www.w3.org/2000/svg' style='display:block;margin:0 auto;'>";
        $svg[] = "<rect x='0' y='0' width='{$size}' height='{$size}' fill='#ffffff'/>";
    
        /* ---------- GRID CIRCLES ---------- */
        foreach ($levels as $level) {
            $r = ($level / 100) * $radius;
            if ($r > 0) { // Skip level 0 circle as it's just a point
                $rFormatted = number_format($r, 2, '.', '');
                $svg[] = "<circle cx='{$center}' cy='{$center}' r='{$rFormatted}' fill='none' stroke='#e5e7eb' stroke-width='1'/>";
            }
        }
    
        /* ---------- AXES + LABELS ---------- */
        foreach ($labels as $i => $label) {
            $value = round($values[$i]);
            $angle = (-pi() / 2) + ($i * $angleStep);
    
            // Axis line
            $x = $center + cos($angle) * $radius;
            $y = $center + sin($angle) * $radius;
            $xFormatted = number_format($x, 2, '.', '');
            $yFormatted = number_format($y, 2, '.', '');
            $svg[] = "<line x1='{$center}' y1='{$center}' x2='{$xFormatted}' y2='{$yFormatted}' stroke='#e5e7eb' stroke-width='1'/>";
    
            // Label position
            $lx = $center + cos($angle) * $labelRadius;
            $ly = $center + sin($angle) * $labelRadius;
            $lxFormatted = number_format($lx, 2, '.', '');
            $lyFormatted = number_format($ly, 2, '.', '');
    
            // Smart alignment
            if (abs(cos($angle)) < 0.3) {
                $anchor = 'middle';
            } elseif (cos($angle) > 0) {
                $anchor = 'start';
            } else {
                $anchor = 'end';
            }
    
            // Truncate long labels (IMPORTANT)
            $shortLabel = mb_strlen($label) > 18
                ? mb_substr($label, 0, 18) . '…'
                : $label;
            
            // Escape HTML entities in label
            $shortLabelEscaped = htmlspecialchars($shortLabel, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $valueEscaped = htmlspecialchars($value . '%', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    
            // Calculate proper Y positions for text (SVG text uses baseline at y coordinate)
            $labelY = number_format($lyFormatted - 6, 2, '.', '');
            $valueY = number_format($lyFormatted + 8, 2, '.', '');
            
            // Label text
            $svg[] = "<text x='{$lxFormatted}' y='{$labelY}' text-anchor='{$anchor}' font-family='DejaVu Sans, Arial, sans-serif' font-size='{$labelFontSize}' font-weight='bold' fill='#0f172a'>{$shortLabelEscaped}</text>";
            
            // Percentage value
            $svg[] = "<text x='{$lxFormatted}' y='{$valueY}' text-anchor='{$anchor}' font-family='DejaVu Sans, Arial, sans-serif' font-size='{$valueFontSize}' font-weight='600' fill='#64748b'>{$valueEscaped}</text>";
        }
    
        /* ---------- DATA POLYGON ---------- */
        $points = [];
        foreach ($values as $i => $value) {
            $angle = (-pi() / 2) + ($i * $angleStep);
            $r = ($value / 100) * $radius;
            $x = $center + cos($angle) * $r;
            $y = $center + sin($angle) * $r;
            $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
        }
    
        // Close polygon (MANDATORY for mPDF)
        if (!empty($points)) {
            $points[] = $points[0];
            $pointsString = implode(' ', $points);
            
            // Use hex colors with fill-opacity for mPDF compatibility
            $svg[] = "<polygon points='{$pointsString}' fill='#ff9800' fill-opacity='0.20' stroke='#ff9800' stroke-width='3'/>";
        }
    
        /* ---------- POINT DOTS ---------- */
        foreach ($values as $i => $value) {
            $angle = (-pi() / 2) + ($i * $angleStep);
            $r = ($value / 100) * $radius;
            $x = $center + cos($angle) * $r;
            $y = $center + sin($angle) * $r;
            $xFormatted = number_format($x, 2, '.', '');
            $yFormatted = number_format($y, 2, '.', '');
    
            $svg[] = "<circle cx='{$xFormatted}' cy='{$yFormatted}' r='4' fill='#ff9800' stroke='#ffffff' stroke-width='2'/>";
        }
    
        /* ---------- RADIAL SCALE LABELS ---------- */
        foreach ($levels as $level) {
            if ($level > 0) { // Skip level 0 label as it's at the center
                $y = $center - ($level / 100) * $radius;
                $yFormatted = number_format($y, 2, '.', '');
                $levelEscaped = htmlspecialchars($level, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $svg[] = "<text x='" . ($center + 10) . "' y='{$yFormatted}' font-size='11' fill='#6b7280' font-family='DejaVu Sans, Arial, sans-serif'>{$levelEscaped}</text>";
            }
        }
    
        $svg[] = "</svg>";
    
        return implode('', $svg);
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
            $defaultSummary = $this->generateDefaultSummary($testResult->user);
            $report = TestReport::create([
                'test_result_id' => $testResult->id,
                'report_summary' => $defaultSummary,
                'generated_at' => now(),
            ]);
        } else {
            // Ensure report has a summary
            $this->ensureReportSummary($report, $testResult->user);
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

        // Calculate SDB scores
        $sdbScores = $this->calculateSDBScores($testResult);
        $sdbPercentage = $sdbScores['percentage'] ?? null;
        $radarClusterSvg = $this->generateRadarChartSvg($clusterScores);
        $radarConstructSvg = $this->generateRadarChartSvg($constructScores);


        $data = [
            'user'                       => $testResult->user,
            'testResult'                 => $testResult,
            'test'                        => $testResult->test,
            'report'                      => $report,
            'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
            'generatedAt'                => ($testResult->created_at ?? now())->format('F d, Y'),
            'clusterScores'              => $clusterScores,
            'constructScores'            => $constructScores,
            'reportSummary'              => $report->report_summary,
            'logoBase64'                 => $logoBase64,
            'radarClusterChartBase64'    => $radarClusterSvg,
            'radarConstructChartBase64'  => $radarConstructSvg,
            'sdbPercentage'              => $sdbPercentage,
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
            $filename = 'axis-strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '.pdf';
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
     * Generate PDF reports and send via email for multiple test results (bulk)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendBulkPdfByEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'test_result_ids' => 'required|array',
            'test_result_ids.*' => 'required|integer|exists:test_results,id',
            'pdf_type' => 'required|string|in:short,full,both', // PDF type: short, full, or both
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => [],
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $testResultIds = $request->input('test_result_ids');
        $pdfType = $request->input('pdf_type', 'full'); // short, full, or both

        $results = [
            'success' => [],
            'failed' => [],
            'total' => 0,
            'success_count' => 0,
            'failed_count' => 0,
        ];

        foreach ($testResultIds as $testResultId) {
            try {
                $testResult = TestResult::with(['user', 'test', 'report'])
                    ->find($testResultId);

                if (!$testResult) {
                    $results['failed'][] = [
                        'test_result_id' => $testResultId,
                        'error' => 'Test result not found',
                    ];
                    $results['failed_count']++;
                    $results['total']++;
                    continue;
                }

                // Send email for this test result
                $emailResult = $this->sendPdfEmailForTestResult($testResult, $pdfType);
                
                if ($emailResult['success']) {
                    // Refresh report to get updated email_sent_at
                   // Reload the test result to get updated report relationship
                    $testResult->load('report');
                    
                    // Get email_sent_at safely
                    $emailSentAt = null;
                    if ($testResult->report) {
                        $testResult->report->refresh();
                        $emailSentAt = $testResult->report->email_sent_at ? $testResult->report->email_sent_at->toISOString() : null;
                    }
                    
                    $results['success'][] = [
                        'test_result_id' => $testResult->id,
                        'user_id' => $testResult->user_id,
                        'user_email' => $testResult->user->email ?? null,
                        'user_name' => $testResult->user->name ?? 
                                     trim(($testResult->user->first_name ?? '') . ' ' . ($testResult->user->last_name ?? '')),
                        'email_sent_at' => $testResult->report->email_sent_at ? $testResult->report->email_sent_at->toISOString() : null,
                        'message' => $emailResult['message'],
                    ];
                    $results['success_count']++;
                } else {
                    $results['failed'][] = [
                        'test_result_id' => $testResult->id,
                        'user_id' => $testResult->user_id,
                        'user_email' => $testResult->user->email ?? null,
                        'error' => $emailResult['message'],
                    ];
                    $results['failed_count']++;
                }
                $results['total']++;

            } catch (\Exception $e) {
                \Log::error('Bulk PDF Email error for test result', [
                    'test_result_id' => $testResultId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $results['failed'][] = [
                    'test_result_id' => $testResultId,
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
     * @param string $pdfType 'short', 'full', or 'both'
     * @return array
     */
    private function sendPdfEmailForTestResult($testResult, $pdfType = 'full')
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
                $defaultSummary = $this->generateDefaultSummary($testResult->user);
                $report = TestReport::create([
                    'test_result_id' => $testResult->id,
                    'report_summary' => $defaultSummary,
                    'generated_at' => now(),
                ]);
            } else {
                // Ensure report has a summary
                $this->ensureReportSummary($report, $testResult->user);
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

            // Calculate SDB scores
            $sdbScores = $this->calculateSDBScores($testResult);
            $sdbPercentage = $sdbScores['percentage'] ?? null;

            // Get user name and source slug for filenames
            $sanitizedUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName);
            $sourceSlug = $this->getReportSourceSlug($testResult);

            /* -----------------------------------------
            GENERATE PDF(s) USING mPDF
            ----------------------------------------- */

            $pdfAttachments = [];
            $footerHtml = '
            <div style="
                font-size: 7pt;
                color: #6c757d;
                text-align: center;
                line-height: 1.2;
                padding: 8px 10px;
                border-top: 1px solid #e9ecef;
            ">
            <p><b>Disclaimer:</b></p>
                You have consented and taken this assessment for personal development purposes only. You understand results are not diagnostic, medical, or clinical, and represent self reported
tendencies. These results may be influenced by context, mood, and self perception. Use them as a starting point for reflection and coaching, not as a definitive judgment. For mental health
or medical concerns, consult a qualified professional. For any queries regarding the report, please send an email to: <b>guide@axiscompass.in</b>
            </div>';

            // Set up mPDF configuration
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];
            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            // Generate Short PDF if needed
            if ($pdfType === 'short' || $pdfType === 'both') {
                $shortData = [
                    'user'                       => $testResult->user,
                    'testResult'                 => $testResult,
                    'test'                        => $testResult->test,
                    'report'                      => $report,
                    'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
                    'generatedAt'                => ($testResult->created_at ?? now())->format('F d, Y'),
                    'clusterScores'              => $clusterScores,
                    'reportSummary'              => $report->report_summary,
                    'logoBase64'                 => $logoBase64,
                    'sdbPercentage'              => $sdbPercentage,
                ];

                $shortHtml = view('reports.mpdf-short-report', $shortData)->render();

                $shortMpdf = new Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 15,
                    'margin_bottom' => 15,
                    'margin_header' => 10,
                    'margin_footer' => 10,
                    'tempDir' => storage_path('app/temp'),
                ]);

                $shortMpdf->SetTitle('Axis Strengths Compass Report');
                $shortMpdf->SetAuthor('Axis Strengths Compass');
                $shortMpdf->SetCreator('Axis Strengths Compass System');
                $shortMpdf->SetHTMLFooter($footerHtml);
                $shortMpdf->WriteHTML($shortHtml);

                $shortFilename = 'axis-strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '-' . $sourceSlug . '-short.pdf';
                $shortOutput = $shortMpdf->Output('', 'S');
                $pdfAttachments[] = [
                    'data' => $shortOutput,
                    'filename' => $shortFilename,
                ];
            }

            // Generate Full PDF if needed
            if ($pdfType === 'full' || $pdfType === 'both') {
                $radarClusterImage = $this->generateRadarmpdfChartSvg($clusterScores);
                $radarConstructImage = $this->generateRadarmpdfChartSvg($constructScores);

                $fullData = [
                    'user'                       => $testResult->user,
                    'testResult'                 => $testResult,
                    'test'                        => $testResult->test,
                    'report'                      => $report,
                    'testName'                   => $testResult->test->title ?? 'Strengths Assessment',
                    'generatedAt'                => ($testResult->created_at ?? now())->format('F d, Y'),
                    'clusterScores'              => $clusterScores,
                    'constructScores'            => $constructScores,
                    'reportSummary'              => $report->report_summary,
                    'logoBase64'                 => $logoBase64,
                    'radarClusterChartBase64'    => $radarClusterImage,
                    'radarConstructChartBase64'  => $radarConstructImage,
                    'sdbPercentage'              => $sdbPercentage,
                ];

                $fullHtml = view('reports.mpdf-report', $fullData)->render();

                $fullMpdf = new Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 15,
                    'margin_bottom' => 15,
                    'margin_header' => 10,
                    'margin_footer' => 10,
                    'tempDir' => storage_path('app/temp'),
                ]);

                $fullMpdf->SetTitle('Axis Strengths Compass Report');
                $fullMpdf->SetAuthor('Axis Strengths Compass');
                $fullMpdf->SetCreator('Axis Strengths Compass System');
                $fullMpdf->SetHTMLFooter($footerHtml);
                $fullMpdf->WriteHTML($fullHtml);

                $fullFilename = 'axis-strengths-compass-report-' . $sanitizedUserName . '-' . $testResult->id . '-' . $sourceSlug . '-full.pdf';
                $fullOutput = $fullMpdf->Output('', 'S');
                $pdfAttachments[] = [
                    'data' => $fullOutput,
                    'filename' => $fullFilename,
                ];

                // Save full PDF to storage
                Storage::disk('public')->put('reports/' . $fullFilename, $fullOutput);
                $report->update([
                    'report_file' => 'reports/' . $fullFilename,
                    'generated_at' => now(),
                ]);
            }

            /* -----------------------------------------
            SEND EMAIL WITH PDF ATTACHMENT(S)
            ----------------------------------------- */

            if (empty($pdfAttachments)) {
                return [
                    'success' => false,
                    'message' => 'No PDF generated. Invalid pdf_type specified.',
                ];
            }

            $userEmail = $testResult->user->email;
            $userDisplayName = $userName;
            $testName = $testResult->test->title ?? 'Axis Strengths Assessment';

            // Build email content based on PDF type
            $emailContent = $this->buildEmailContent($userDisplayName, $testName, $pdfType);

            Mail::send([], [], function ($message) use ($userEmail, $userDisplayName, $pdfAttachments, $testName, $emailContent) {
                $message->to($userEmail, $userDisplayName)
                    ->subject('Your Strengths Compass Assessment Report - ' . $testName);

                // Attach all PDFs
                foreach ($pdfAttachments as $attachment) {
                    $message->attachData($attachment['data'], $attachment['filename'], [
                        'mime' => 'application/pdf',
                    ]);
                }

                $message->html($emailContent);
            });

            // Update email_sent_at status after successful email send
            $report->update([
                'email_sent_at' => now(),
            ]);

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

    /**
     * Calculate SDB (Social Desirability Bias) scores for a test result
     * Returns: ['raw' => float, 'percentage' => float, 'band' => string, 'band_name' => string]
     */
    private function calculateSDBScores(TestResult $testResult): array
    {
        // Load answers with questions if not already loaded
        if (!$testResult->relationLoaded('answers')) {
            $testResult->load('answers.question');
        }

        // Format questions with answers similar to getTestResultAnswers
        $questions = $testResult->answers->map(function ($answer) {
            $question = $answer->question;
            if (!$question) {
                return null;
            }

            return [
                'question_id' => $question->id,
                'category' => $question->category,
                'answer' => [
                    'answer_value' => $answer->answer_value,
                ],
            ];
        })->filter()->values()->toArray();

        // Ensure it's an array
        if (!is_array($questions) || empty($questions)) {
            return [
                'raw' => null,
                'percentage' => null,
                'band' => null,
                'band_name' => null,
            ];
        }

        // Filter SDB questions
        $sdbQuestions = array_filter($questions, function ($question) {
            return isset($question['category']) && strtoupper($question['category']) === 'SDB';
        });
        
        // Reset array keys to ensure sequential indexing
        $sdbQuestions = array_values($sdbQuestions);

        if (empty($sdbQuestions)) {
            return [
                'raw' => null,
                'percentage' => null,
                'band' => null,
                'band_name' => null,
            ];
        }

        // Sum all SDB answer values (not final_score, as SDB uses direct scoring)
        $sdbSum = 0;
        $sdbCount = 0;
        
        foreach ($sdbQuestions as $question) {
            $answerValue = data_get($question, 'answer.answer_value');
            if ($answerValue !== null && is_numeric($answerValue)) {
                $sdbSum += (float) $answerValue;
                $sdbCount++;
            }
        }

        if ($sdbCount === 0) {
            return [
                'raw' => null,
                'percentage' => null,
                'band' => null,
                'band_name' => null,
            ];
        }

        // Calculate raw score (average)
        $sdbRaw = $sdbSum / $sdbCount;
        $sdbRaw = round($sdbRaw, 2);

        // Calculate percentage: ((raw - 1) / 4) * 100
        $sdbPercentage = $this->calculatePercentageFromMean($sdbRaw);

        // Determine band based on raw score
        $band = $this->determineSDBBand($sdbRaw, $sdbPercentage);

        return [
            'raw' => $sdbRaw,
            'percentage' => $sdbPercentage,
            'band' => is_array($band) ? ($band['band'] ?? null) : null,
            'band_name' => is_array($band) ? ($band['name'] ?? null) : null,
        ];
    }

    /**
     * Calculate percentage from mean score: ((raw - 1) / 4) * 100
     */
    private function calculatePercentageFromMean($meanScore)
    {
        if ($meanScore <= 0) {
            return 0;
        }
        
        // Step 1: Subtract 1
        $step1 = $meanScore - 1;
        
        // Step 2: Divide by 4
        $step2 = $step1 / 4;
        
        // Step 3: Convert to percentage and round
        $percentage = round($step2 * 100);
        
        return max(0, min(100, (int) $percentage));
    }

    /**
     * Determine SDB band based on raw score and percentage
     * GREEN (Authentic): 1.0-3.8 (0-70%)
     * AMBER (Managing): 3.81-4.4 (71-85%)
     * RED (Idealized): 4.41-5.0 (86-100%)
     */
    private function determineSDBBand($rawScore, $percentage): array
    {
        // Handle null or invalid values
        if ($rawScore === null || !is_numeric($rawScore)) {
            return [
                'band' => null,
                'name' => null,
            ];
        }

        $rawScore = (float) $rawScore;

        if ($rawScore >= 1.0 && $rawScore <= 3.8) {
            return [
                'band' => 'GREEN',
                'name' => 'GREEN (Authentic)',
            ];
        } elseif ($rawScore >= 3.81 && $rawScore <= 4.4) {
            return [
                'band' => 'AMBER',
                'name' => 'AMBER (Managing)',
            ];
        } elseif ($rawScore >= 4.41 && $rawScore <= 5.0) {
            return [
                'band' => 'RED',
                'name' => 'RED (Idealized)',
            ];
        } else {
            // Fallback for edge cases
            return [
                'band' => 'UNKNOWN',
                'name' => 'UNKNOWN',
            ];
        }
    }

    /**
     * Build email content based on PDF type
     * 
     * @param string $userDisplayName
     * @param string $testName
     * @param string $pdfType 'short', 'full', or 'both'
     * @return string
     */
    private function buildEmailContent($userDisplayName, $testName, $pdfType)
    {
        $reportDescription = '';
        $reportItems = '';

        if ($pdfType === 'short') {
            $reportDescription = 'Your short report contains a summary of your assessment results, including cluster-level insights and personalized guidance.';
            $reportItems = '
                <li>Cover page with your details</li>
                <li>Report summary with cluster overview</li>
                <li>Strengths to Leverage and Emerging Capabilities</li>
                <li>Personalized guidance (if applicable)</li>';
        } elseif ($pdfType === 'full') {
            $reportDescription = 'Your complete report contains detailed insights about your strengths across six clusters and 18 psychological constructs, along with radar charts and personalized recommendations.';
            $reportItems = '
                <li>Cover page with your details</li>
                <li>Report summary with cluster overview</li>
                <li>Detailed cluster-level analysis with priority bands</li>
                <li>Construct-level detailed insights</li>
                <li>Visual radar charts</li>
                <li>Personalized tendencies and recommendations</li>';
        } else { // both
            $reportDescription = 'Your reports contain both a quick summary and a detailed analysis of your assessment results.';
            $reportItems = '
                <li><strong>Short Report:</strong> Cover page, summary, cluster overview, and guidance</li>
                <li><strong>Full Report:</strong> All of the above plus detailed cluster/construct analysis and radar charts</li>';
        }

        return '
            <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;">
                    <h1 style="margin: 0; font-size: 24px;">Axis Strengths Compass</h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Assessment Report</p>
                </div>
                <div style="padding: 30px; background: #ffffff;">
                    <h2 style="color: #667eea; margin-top: 0;">Your Assessment Report is Ready</h2>
                    <p>Dear ' . htmlspecialchars($userDisplayName) . ',</p>
                    <p>Thank you for completing the Strengths Compass Assessment. Your personalized report' . ($pdfType === 'both' ? 's are' : ' is') . ' attached to this email.</p>
                    <p>' . $reportDescription . '</p>
                    <p><strong>Please review the attached PDF report' . ($pdfType === 'both' ? 's' : '') . ' for your complete assessment results.</strong></p>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0; color: #555; font-size: 14px;">
                            <strong>What\'s included in your report' . ($pdfType === 'both' ? 's' : '') . ':</strong>
                        </p>
                        <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #555;">' . $reportItems . '
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
            </div>';
    }

    /**
     * Generate default summary text dynamically based on user name
     * 
     * @param \App\Models\User|null $user
     * @return string
     */
    private function generateDefaultSummary($user = null)
    {
        $candidateName = 'the candidate';
        
        if ($user) {
            if (isset($user->name) && !empty($user->name)) {
                $candidateName = $user->name;
            } elseif (isset($user->first_name) || isset($user->last_name)) {
                $candidateName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            }
        }
        
        return "This summary presents the cluster-level results from {$candidateName}'s Strengths Compass Assessment. The assessment measures 18 psychological constructs grouped into six clusters. Based on percentage scores, each cluster falls into one of three bands: HIGH - indicates a core strength to leverage, MEDIUM - indicates an emerging capability, and LOW - indicates a development priority. The clusters are presented below in two groups — Strengths and Developing Areas.";
    }

    /**
     * Ensure report has a summary (set default if null)
     * 
     * @param \App\Models\TestReport $report
     * @param \App\Models\User|null $user
     * @return void
     */
    private function ensureReportSummary($report, $user = null)
    {
        if (empty($report->report_summary)) {
            $report->report_summary = $this->generateDefaultSummary($user);
            $report->save();
        }
    }

    /**
     * Strip embedded disclaimer from report summary (used for PDF - disclaimer shown in footer).
     *
     * @param string $content
     * @return string
     */
    private function stripDisclaimerFromSummary(string $content): string
    {
        if (empty(trim($content))) {
            return $content;
        }

        $patterns = [
            '/<div[^>]*class="[^"]*disclaimer[^"]*"[^>]*>.*?<\/div>/is',
            '/<p[^>]*>\s*<b>Disclaimer:?<\/b>\s*<\/p>\s*<p[^>]*>.*?personal development purposes only.*?<\/p>/is',
            '/<p[^>]*>\s*<strong>Disclaimer:?<\/strong>\s*<\/p>\s*<p[^>]*>.*?personal development purposes only.*?<\/p>/is',
            '/<div[^>]*>\s*<p[^>]*>\s*<b>Disclaimer:?<\/b>\s*<\/p>.*?personal development purposes only.*?<\/div>/is',
            '/\s*<strong>Disclaimer:?<\/strong>.*?@[a-zA-Z0-9.]+\.(in|ai|com)\s*/is',
            '/\s*<b>Disclaimer:?<\/b>.*?@[a-zA-Z0-9.]+\.(in|ai|com)\s*/is',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        return trim($content);
    }

    /**
     * Get combined answers for a test result (SC Pro + CERC for CERC tests)
     * Similar to TestTakingController::getCombinedAnswersForResult but returns UserAnswer models
     */
    private function getCombinedAnswersForReport($testResult)
    {
        $test = $testResult->test;
        $userId = $testResult->user_id;

        // For non-CERC tests, just return the test result's answers
        if ($test->source !== 'CERC') {
            return $testResult->answers;
        }

        // For CERC tests, combine SC Pro answers with CERC answers
        // Find SC Pro test - use explicit mapping if available
        if ($test->sc_pro_test_id) {
            $scProTest = Test::where('id', $test->sc_pro_test_id)
                ->where('source', 'SC Pro')
                ->where('is_active', true)
                ->first();
        } else {
            $scProTest = Test::where('source', 'SC Pro')
                ->where('is_active', true)
                ->where('age_group_id', $test->age_group_id)
                ->first();
        }

        // Get CERC test result answers (new CERC questions)
        $cercAnswers = $testResult->answers->keyBy('question_id');
        
        // Get CERC test questions with their texts and relationships
        $cercTestQuestions = $test->selectedQuestions()
            ->with('construct.cluster')
            ->get()
            ->keyBy('id');
        $cercTestQuestionIds = $cercTestQuestions->pluck('id')->toArray();

        $combinedAnswers = collect();

        // If SC Pro test exists, get its answers and match them to CERC questions
        if ($scProTest) {
            $scProTestResult = TestResult::where('user_id', $userId)
                ->where('test_id', $scProTest->id)
                ->where('status', 'completed')
                ->latest()
                ->first();

            if ($scProTestResult) {
                // Get all SC Pro answers with question details for text-based matching
                $scProAnswers = UserAnswer::where('test_result_id', $scProTestResult->id)
                    ->with('question:id,question_text,construct_id,category')
                    ->get();

                // Create maps for matching
                $scProAnswersById = [];
                $scProAnswersByText = [];
                
                foreach ($scProAnswers as $scProAnswer) {
                    if ($scProAnswer->question) {
                        // Store by ID for exact matches
                        $scProAnswersById[$scProAnswer->question_id] = $scProAnswer;
                        
                        // Store by normalized text for text-based matching
                        $normalizedText = $this->normalizeQuestionText($scProAnswer->question->question_text);
                        if (!isset($scProAnswersByText[$normalizedText])) {
                            $scProAnswersByText[$normalizedText] = $scProAnswer;
                        }
                    }
                }

                // Match SC Pro answers to CERC questions
                foreach ($cercTestQuestionIds as $cercQuestionId) {
                    $cercQuestion = $cercTestQuestions->get($cercQuestionId);
                    if (!$cercQuestion) {
                        continue;
                    }

                    $matchedAnswer = null;

                    // Check if there's a CERC answer for this question (newly answered)
                    if ($cercAnswers->has($cercQuestionId)) {
                        $matchedAnswer = $cercAnswers->get($cercQuestionId);
                        // Ensure question is loaded with relationships
                        if (!$matchedAnswer->relationLoaded('question') || !$matchedAnswer->question) {
                            $matchedAnswer->load('question.construct.cluster');
                        }
                        $combinedAnswers->push($matchedAnswer);
                    }
                    // Check if there's an SC Pro answer with matching question ID
                    elseif (isset($scProAnswersById[$cercQuestionId])) {
                        $scProAnswer = $scProAnswersById[$cercQuestionId];
                        // Create a new UserAnswer instance with SC Pro answer data but CERC question
                        $matchedAnswer = new UserAnswer();
                        $matchedAnswer->id = $scProAnswer->id;
                        $matchedAnswer->test_result_id = $scProAnswer->test_result_id;
                        $matchedAnswer->question_id = $cercQuestionId; // Use CERC question ID
                        $matchedAnswer->answer_value = $scProAnswer->answer_value;
                        $matchedAnswer->final_score = $scProAnswer->final_score;
                        $matchedAnswer->setRelation('question', $cercQuestion);
                        $combinedAnswers->push($matchedAnswer);
                    }
                    // If no ID match, check by question text
                    elseif (!empty($scProAnswersByText)) {
                        $normalizedCercText = $this->normalizeQuestionText($cercQuestion->question_text);
                        if (isset($scProAnswersByText[$normalizedCercText])) {
                            $scProAnswer = $scProAnswersByText[$normalizedCercText];
                            // Create a new UserAnswer instance with SC Pro answer data but CERC question
                            $matchedAnswer = new UserAnswer();
                            $matchedAnswer->id = $scProAnswer->id;
                            $matchedAnswer->test_result_id = $scProAnswer->test_result_id;
                            $matchedAnswer->question_id = $cercQuestionId; // Use CERC question ID
                            $matchedAnswer->answer_value = $scProAnswer->answer_value;
                            $matchedAnswer->final_score = $scProAnswer->final_score;
                            $matchedAnswer->setRelation('question', $cercQuestion);
                            $combinedAnswers->push($matchedAnswer);
                        }
                    }
                }
            }
        }

        // If no SC Pro test or answers, just return CERC answers
        if ($combinedAnswers->isEmpty() && !$cercAnswers->isEmpty()) {
            return $cercAnswers->map(function ($answer) {
                $answer->load('question.construct.cluster');
                return $answer;
            });
        }

        return $combinedAnswers;
    }

    /**
     * Normalize question text for comparison (trim, lowercase, remove non-alphanumeric)
     */
    private function normalizeQuestionText(string $text): string
    {
        $normalized = strtolower(trim($text));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);
        return $normalized;
    }
}

