<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use App\Models\TestReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

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

        return response()->json([
            'data' => [
                'report' => $report->load('testResult'),
                'test_result' => $testResult,
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
        ];

        // Generate PDF
        $pdf = Pdf::loadView('reports.test-report', $data);
        
        // Set PDF options
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);

        // Generate filename
        $filename = 'test-report-' . $testResult->id . '-' . now()->format('Y-m-d') . '.pdf';

        // Save PDF to storage (optional - for later retrieval)
        $pdfPath = 'reports/' . $filename;
        Storage::disk('public')->put($pdfPath, $pdf->output());

        // Update report with file path
        $report->update([
            'report_file' => $pdfPath,
            'generated_at' => now(),
        ]);

        // Return PDF download response
        return $pdf->download($filename);
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
        ];

        // Generate PDF
        $pdf = Pdf::loadView('reports.test-report', $data);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);

        // Return PDF stream (view in browser)
        return $pdf->stream('test-report-' . $testResult->id . '.pdf');
    }
}

