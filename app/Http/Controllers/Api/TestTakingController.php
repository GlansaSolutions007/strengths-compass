<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Test;
use App\Models\TestResult;
use App\Models\UserAnswer;
use App\Models\OptionsModel;
use App\Models\QuestionsModel;
use App\Models\ScoringRule;
use App\Models\User;
use App\Exports\UserTestDataExport;
use App\Mail\TestCompletionMail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class TestTakingController extends Controller
{
    /**
     * Get test with questions and options for user to take
     */
    public function getTestForUser($testId)
    {
        $test = Test::with(['selectedQuestions.construct.cluster'])
            ->where('is_active', true)
            ->find($testId);

        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found or inactive'
            ], 404);
        }

        // Get all options (same for every question)
        $options = OptionsModel::orderBy('value')->get();

        // Format questions with their order
        $questions = $test->selectedQuestions->map(function ($question) {
            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'category' => $question->category,
                'order_no' => $question->pivot->order_no,
                'construct_id' => $question->construct_id,
                'construct_name' => $question->construct->name ?? null,
                'cluster_id' => $question->pivot->cluster_id ?? null,
            ];
        })->sortBy('order_no')->values();

        return response()->json([
            'status' => true,
            'data' => [
                'test' => [
                    'id' => $test->id,
                    'title' => $test->title,
                    'description' => $test->description,
                ],
                'questions' => $questions,
                'options' => $options,
                'total_questions' => $questions->count()
            ],
            'message' => 'Test fetched successfully'
        ], 200);
    }

    /**
     * Submit test answers and calculate scores
     */
    public function submitAnswers(Request $request, $testId)
    {
        // First verify the test exists
        $test = Test::find($testId);
        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }

        // Get valid question IDs for this test
        $validQuestionIds = $test->selectedQuestions->pluck('id')->toArray();

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'answers' => 'required|array',
            'answers.*.question_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($validQuestionIds, $testId) {
                    if (!in_array($value, $validQuestionIds)) {
                        $fail("The question ID {$value} is not part of test {$testId}.");
                    }
                }
            ],
            'answers.*.answer_value' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
                'hint' => 'Make sure all question_ids belong to this test. Use GET /api/tests/' . $testId . '/take to see valid questions.'
            ], 422);
        }

        $userId = $request->input('user_id');
        $answers = $request->input('answers');

        DB::beginTransaction();
        try {
            // Create test result
            $testResult = TestResult::create([
                'user_id' => $userId,
                'test_id' => $testId,
                'status' => 'completed'
            ]);

            // Process each answer and calculate scores
            $userAnswers = [];
            $totalScore = 0;
            $questionCount = 0;

            foreach ($answers as $answer) {
                $questionId = $answer['question_id'];
                $answerValue = $answer['answer_value'];

                $question = QuestionsModel::with('construct.cluster')->find($questionId);
                if (!$question) {
                    continue;
                }

                // Get scoring rule if exists, otherwise use question category
                $scoringRule = ScoringRule::where('question_id', $questionId)->first();
                $category = $scoringRule->category ?? $question->category;
                $reverseScore = $scoringRule->reverse_score ?? false;
                $weight = $scoringRule->weight ?? 1.0;
                
                // SDB questions are completely excluded from cluster/construct calculations
                if ($category === 'SDB') {
                    $includeInConstruct = false;
                } else {
                    $includeInConstruct = $scoringRule->include_in_construct ?? true;
                }

                // Calculate final score based on category
                $finalScore = $this->calculateScore($answerValue, $category, $reverseScore, $weight);
                $finalScore = round($finalScore, 2); // Round to 2 decimal places

                // Store user answer
                $userAnswer = UserAnswer::create([
                    'test_result_id' => $testResult->id,
                    'question_id' => $questionId,
                    'answer_value' => $answerValue,
                    'final_score' => $finalScore
                ]);

                $userAnswers[] = [
                    'question_id' => $questionId,
                    'answer_value' => $answerValue,
                    'final_score' => $finalScore,
                    'category' => $category,
                    'include_in_construct' => $includeInConstruct
                ];

                // Add to total if included in construct
                if ($includeInConstruct) {
                    $totalScore += $finalScore;
                    $questionCount++;
                }
            }

            // Calculate average score
            $averageScore = $questionCount > 0 ? $totalScore / $questionCount : 0;
            $averageScore = round($averageScore, 2); // Round to 2 decimal places
            $totalScore = round($totalScore, 2); // Round to 2 decimal places
            $averagePercentage = $this->convertToPercentage($averageScore);

            // Calculate cluster and construct scores
            $clusterScores = $this->calculateClusterScores($userAnswers, $test);
            $constructScores = $this->calculateConstructScores($userAnswers, $test);

            // Calculate SDB separately (completely independent from cluster/construct calculations)
            $sdbData = $this->calculateSDBScore($userAnswers);

            // Calculate overall category based on average score (using percentage)
            $overallCategory = $this->categorizeScore($averageScore);

            // Update test result with calculated scores
            $testResult->update([
                'total_score' => $totalScore,
                'average_score' => $averageScore,
                'overall_category' => $overallCategory,
                'cluster_scores' => $clusterScores,
                'construct_scores' => $constructScores,
                'sdb_raw_score' => $sdbData['raw_score'],
                'sdb_percentage' => $sdbData['percentage'],
                'sdb_band' => $sdbData['band'],
                'sdb_flag' => $sdbData['flag']
            ]);

            // Format radar chart data
            $radarChartData = $this->formatRadarChartData($clusterScores);

            DB::commit();

            // Send test completion email to user
            // Use a separate try-catch to ensure test submission doesn't fail if email fails
            try {
                $user = User::find($request->user_id);
                if ($user && !empty($user->email)) {
                    \Log::info('=== TEST SUBMISSION: Starting test completion email send process ===', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'test_id' => $testId,
                        'test_result_id' => $testResult->id,
                    ]);

                    Mail::to($user->email)->send(new TestCompletionMail($user, $test, $testResult));
                    
                    \Log::info('=== TEST SUBMISSION: Test completion email sent successfully ===', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'test_result_id' => $testResult->id,
                    ]);
                } else {
                    \Log::warning('Cannot send test completion email: user not found or email is empty', [
                        'user_id' => $request->user_id,
                    ]);
                }
            } catch (\Throwable $e) {
                // Log the error but don't fail the test submission
                \Log::error('=== TEST SUBMISSION: Failed to send test completion email ===', [
                    'user_id' => $request->user_id ?? null,
                    'test_result_id' => $testResult->id ?? null,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Test submitted successfully',
                'data' => [
                    'test_result_id' => $testResult->id,
                    'total_score' => round($totalScore, 2),
                    'average_score' => round($averageScore, 2),
                    'average_percentage' => round($averagePercentage, 0), // Rounded to whole number
                    'overall_category' => $overallCategory,
                    'cluster_scores' => $clusterScores,
                    'construct_scores' => $constructScores,
                    'sdb' => [
                        'raw_score' => $sdbData['raw_score'],
                        'percentage' => $sdbData['percentage'],
                        'band' => $sdbData['band'],
                        'flag' => $sdbData['flag'],
                        'count' => $sdbData['count']
                    ],
                    'radar_chart' => $radarChartData,
                    'total_questions_answered' => count($answers)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error submitting test: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate score based on category and rules
     */
    private function calculateScore($answerValue, $category, $reverseScore, $weight)
    {
        $baseScore = $answerValue; // 1-5

        // Apply reverse scoring if needed
        if ($reverseScore || $category === 'R') {
            // Reverse: 1->5, 2->4, 3->3, 4->2, 5->1
            $baseScore = 6 - $answerValue;
        }

        // Apply weight
        $finalScore = $baseScore * $weight;

        return $finalScore;
    }

    /**
     * Convert score from 1-5 scale to percentage
     * Formula: ((score - 1) / 4) * 100
     * Example: 3.57 -> ((3.57 - 1) / 4) * 100 = 64.25%
     */
    private function convertToPercentage($score)
    {
        if ($score <= 0) {
            return 0;
        }
        $percentage = (($score - 1) / 4) * 100;
        return round($percentage, 2);
    }

    /**
     * Categorize based on percentage: 0-59 = low, 60-79 = medium, 80-100 = high
     */
    private function categorizeScore($score)
    {
        // Convert to percentage first
        $percentage = $this->convertToPercentage($score);
        
        if ($percentage < 60) {
            return 'low';
        } elseif ($percentage < 80) {
            return 'medium';
        } else {
            return 'high';
        }
    }

    /**
     * Calculate cluster scores (both totals and averages)
     */
private function calculateClusterScores($userAnswers, $test)
    {
        $clusterScores = [];
        $clusterTotals = [];
        $clusterCounts = [];

        foreach ($userAnswers as $answer) {
            if (!$answer['include_in_construct']) {
                continue; // Skip SDB questions that aren't included
            }

            $question = QuestionsModel::with('construct.cluster')->find($answer['question_id']);
            if (!$question || !$question->construct || !$question->construct->cluster) {
                continue;
            }

            $clusterName = $question->construct->cluster->name;
            $clusterId = $question->construct->cluster->id;

            if (!isset($clusterTotals[$clusterId])) {
                $clusterTotals[$clusterId] = 0;
                $clusterCounts[$clusterId] = 0;
            }

            $clusterTotals[$clusterId] += $answer['final_score'];
            $clusterCounts[$clusterId]++;
        }

        // Calculate both total and average scores per cluster
        foreach ($clusterTotals as $clusterId => $total) {
            $count = $clusterCounts[$clusterId];
            $average = $count > 0 ? $total / $count : 0;
            $average = round($average, 2);
            $percentage = $this->convertToPercentage($average);
            
            $cluster = $test->clusters->find($clusterId);
            $clusterName = $cluster ? $cluster->name : "Cluster {$clusterId}";
            
            $clusterScores[$clusterName] = [
                'total' => round($total, 2),
                'average' => $average,
                'percentage' => $percentage,
                'count' => $count,
                'category' => $this->categorizeScore($average)
            ];
        }

        return $clusterScores;
    }

    /**
     * Calculate construct scores (both totals and averages)
     */
    private function calculateConstructScores($userAnswers, $test)
    {
        $constructScores = [];
        $constructTotals = [];
        $constructCounts = [];
        $constructNames = [];

        foreach ($userAnswers as $answer) {
            if (!$answer['include_in_construct']) {
                continue; // Skip SDB questions that aren't included
            }

            $question = QuestionsModel::with('construct')->find($answer['question_id']);
            if (!$question || !$question->construct) {
                continue;
            }

            $constructId = $question->construct_id;
            $constructName = $question->construct->name;

            if (!isset($constructTotals[$constructId])) {
                $constructTotals[$constructId] = 0;
                $constructCounts[$constructId] = 0;
                $constructNames[$constructId] = $constructName;
            }

            $constructTotals[$constructId] += $answer['final_score'];
            $constructCounts[$constructId]++;
        }

        // Calculate both total and average scores per construct
        foreach ($constructTotals as $constructId => $total) {
            $count = $constructCounts[$constructId];
            $average = $count > 0 ? $total / $count : 0;
            $average = round($average, 2);
            $percentage = $this->convertToPercentage($average);
            $constructName = $constructNames[$constructId] ?? "Construct {$constructId}";
            
            $constructScores[$constructName] = [
                'total' => round($total, 2),
                'average' => $average,
                'percentage' => $percentage,
                'count' => $count,
                'category' => $this->categorizeScore($average)
            ];
        }

        return $constructScores;
    }

    /**
     * Calculate SDB score separately
     * Step 1: Sum all SDB items (18 items)
     * Step 2: Calculate average: SDB (Raw) = Sum of 18 items / 18
     * Step 3: Convert to percentage: ((raw_score - 1) / 4) * 100
     * Step 4: Categorize into bands:
     *   - GREEN (Authentic): 0-70% (Raw Score 1.0-3.8)
     *   - AMBER (Managing): 71-85% (Raw Score 3.81-4.4)
     *   - RED (Idealized): 86-100% (Raw Score 4.41-5.0)
     */
    private function calculateSDBScore($userAnswers)
    {
        // Filter only SDB answers
        $sdbAnswers = array_filter($userAnswers, function ($answer) {
            return $answer['category'] === 'SDB';
        });

        if (count($sdbAnswers) === 0) {
            return [
                'raw_score' => null,
                'percentage' => null,
                'band' => null,
                'flag' => false
            ];
        }

        // Step 1: Sum all SDB final scores
        $sdbSum = 0;
        $sdbCount = 0;
        foreach ($sdbAnswers as $answer) {
            $sdbSum += $answer['final_score'];
            $sdbCount++;
        }

        // Step 2: Calculate average (Raw Score)
        $sdbRawScore = $sdbCount > 0 ? $sdbSum / $sdbCount : 0;
        $sdbRawScore = round($sdbRawScore, 2);

        // Step 3: Convert to percentage
        $sdbPercentage = $this->convertToPercentage($sdbRawScore);
        $sdbPercentage = round($sdbPercentage, 0); // Round to whole number

        // Step 4: Categorize into bands
        $sdbBand = $this->categorizeSDBBand($sdbPercentage);

        // Flag: RED band indicates invalid/idealized responses
        $sdbFlag = ($sdbBand === 'RED');

        return [
            'raw_score' => $sdbRawScore,
            'percentage' => $sdbPercentage,
            'band' => $sdbBand,
            'flag' => $sdbFlag,
            'count' => $sdbCount
        ];
    }

    /**
     * Categorize SDB percentage into bands based on image specifications:
     * - GREEN (Authentic): 0-70% (Raw Score 1.0-3.8)
     * - AMBER (Managing): 71-85% (Raw Score 3.81-4.4)
     * - RED (Idealized): 86-100% (Raw Score 4.41-5.0)
     */
    private function categorizeSDBBand($percentage)
    {
        if ($percentage <= 70) {
            return 'GREEN';
        } elseif ($percentage <= 85) {
            return 'AMBER';
        } else {
            return 'RED';
        }
    }

    /**
     * Format cluster scores for radar chart
     * Returns data in format: { labels: [], datasets: [{ label: string, data: [] }] }
     */
    private function formatRadarChartData($clusterScores)
    {
        if (empty($clusterScores) || !is_array($clusterScores)) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Cluster Scores',
                        'data' => [],
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 2,
                        'pointBackgroundColor' => 'rgba(54, 162, 235, 1)',
                        'pointBorderColor' => '#fff',
                        'pointHoverBackgroundColor' => '#fff',
                        'pointHoverBorderColor' => 'rgba(54, 162, 235, 1)',
                    ]
                ]
            ];
        }

        $labels = [];
        $data = [];
        $maxValue = 0;

        // Extract labels and average scores from cluster_scores
        foreach ($clusterScores as $clusterName => $scores) {
            $labels[] = $clusterName;
            $average = $scores['average'] ?? 0;
            $data[] = round($average, 2);
            
            // Track max value for scaling
            if ($average > $maxValue) {
                $maxValue = $average;
            }
        }

        // Calculate maxValue: use fixed 5 for consistency, or ceil(max + 0.5) to add padding
        // This ensures the chart scale is consistent and has some visual padding
        $chartMaxValue = 5; // Fixed scale for 1-5 scoring system
        if ($maxValue > 5) {
            // If scores exceed 5 (due to weighting), use calculated max with padding
            $chartMaxValue = ceil($maxValue + 0.5);
        } elseif ($maxValue > 0 && $maxValue < 5) {
            // If all scores are low, still use 5 for consistent scale across charts
            $chartMaxValue = 5;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Cluster Scores',
                    'data' => $data,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 2,
                    'pointBackgroundColor' => 'rgba(54, 162, 235, 1)',
                    'pointBorderColor' => '#fff',
                    'pointHoverBackgroundColor' => '#fff',
                    'pointHoverBorderColor' => 'rgba(54, 162, 235, 1)',
                ]
            ],
            'maxValue' => $chartMaxValue,
        ];
    }

    /**
     * Get test results for a user (lightweight - scores only)
     */
    public function getResults($testResultId)
    {
        $testResult = TestResult::with(['test', 'user'])
            ->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'status' => false,
                'message' => 'Test result not found'
            ], 404);
        }

        // Format radar chart data from cluster scores
        $radarChartData = $this->formatRadarChartData($testResult->cluster_scores);

        return response()->json([
            'status' => true,
            'data' => [
                'test_result_id' => $testResult->id,
                'test' => [
                    'id' => $testResult->test->id,
                    'title' => $testResult->test->title,
                    'description' => $testResult->test->description,
                ],
                'user' => [
                    'id' => $testResult->user->id,
                    'name' => $testResult->user->name,
                    'email' => $testResult->user->email,
                ],
                'scores' => [
                    'total_score' => $testResult->total_score,
                    'average_score' => $testResult->average_score,
                    'average_percentage' => round($this->convertToPercentage($testResult->average_score ?? 0), 0), // Rounded to whole number
                    'overall_category' => $testResult->overall_category ?? $this->categorizeScore($testResult->average_score ?? 0),
                    'cluster_scores' => $testResult->cluster_scores,
                    'construct_scores' => $testResult->construct_scores,
                    'sdb' => [
                        'raw_score' => $testResult->sdb_raw_score,
                        'percentage' => $testResult->sdb_percentage,
                        'band' => $testResult->sdb_band,
                        'flag' => $testResult->sdb_flag,
                    ],
                ],
                'radar_chart' => $radarChartData,
                'status' => $testResult->status,
                'submitted_at' => $testResult->created_at,
            ],
            'message' => 'Test result fetched successfully'
        ], 200);
    }

    /**
     * Get questions and answers for a specific test result
     * Use this endpoint to fetch detailed question/answer data separately
     */
    public function getTestResultAnswers($testResultId)
    {
        $testResult = TestResult::with(['test', 'user', 'answers.question.construct.cluster'])
            ->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'status' => false,
                'message' => 'Test result not found'
            ], 404);
        }

        // Get all options for answer labels
        $options = OptionsModel::orderBy('value')->get()->keyBy('value');

        // Get test's question order for proper sorting
        $test = $testResult->test;
        $test->load('selectedQuestions');
        $questionOrder = $test->selectedQuestions->pluck('pivot.order_no', 'id')->toArray();

        // Format questions with answers
        $questionsWithAnswers = $testResult->answers->map(function ($answer) use ($options, $questionOrder) {
            $question = $answer->question;
            $optionLabel = $options->get($answer->answer_value);
            
            return [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'category' => $question->category,
                'order_no' => $questionOrder[$question->id] ?? null,
                'construct' => $question->construct ? [
                    'id' => $question->construct->id,
                    'name' => $question->construct->name,
                    'cluster' => $question->construct->cluster ? [
                        'id' => $question->construct->cluster->id,
                        'name' => $question->construct->cluster->name,
                    ] : null,
                ] : null,
                'answer' => [
                    'answer_value' => $answer->answer_value,
                    'answer_label' => $optionLabel ? $optionLabel->label : null,
                    'final_score' => $answer->final_score,
                ],
            ];
        })->sortBy(function ($item) use ($questionOrder) {
            // Sort by test's question order if available, otherwise by question_id
            return $item['order_no'] ?? $item['question_id'];
        })->values();

        return response()->json([
            'status' => true,
            'data' => [
                'test_result_id' => $testResult->id,
                'test' => [
                    'id' => $testResult->test->id,
                    'title' => $testResult->test->title,
                ],
                'user' => [
                    'id' => $testResult->user->id,
                    'name' => $testResult->user->name,
                ],
                'questions_with_answers' => $questionsWithAnswers,
                'total_questions' => $questionsWithAnswers->count(),
            ],
            'message' => 'Questions and answers fetched successfully'
        ], 200);
    }

    /**
     * Get all test results for a user (lightweight - scores only)
     */
    public function getUserResults($userId)
    {
        $testResults = TestResult::with(['test'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedResults = $testResults->map(function ($testResult) {
            $radarChartData = $this->formatRadarChartData($testResult->cluster_scores);
            
            return [
                'test_result_id' => $testResult->id,
                'test' => [
                    'id' => $testResult->test->id,
                    'title' => $testResult->test->title,
                ],
                'scores' => [
                    'total_score' => $testResult->total_score,
                    'average_score' => $testResult->average_score,
                    'average_percentage' => round($this->convertToPercentage($testResult->average_score ?? 0), 0), // Rounded to whole number
                    'cluster_scores' => $testResult->cluster_scores,
                    'construct_scores' => $testResult->construct_scores,
                    'sdb' => [
                        'raw_score' => $testResult->sdb_raw_score,
                        'percentage' => $testResult->sdb_percentage,
                        'band' => $testResult->sdb_band,
                        'flag' => $testResult->sdb_flag,
                    ],
                ],
                'radar_chart' => $radarChartData,
                'status' => $testResult->status,
                'submitted_at' => $testResult->created_at,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $formattedResults,
            'message' => 'User test results fetched successfully'
        ], 200);
    }

    /**
     * Get all test results for a specific test (lightweight - scores only)
     */
    public function getTestResults($testId)
    {
        $testResults = TestResult::with(['user'])
            ->where('test_id', $testId)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedResults = $testResults->map(function ($testResult) {
            $radarChartData = $this->formatRadarChartData($testResult->cluster_scores);
            
            return [
                'test_result_id' => $testResult->id,
                'user' => [
                    'id' => $testResult->user->id,
                    'name' => $testResult->user->name,
                    'email' => $testResult->user->email,
                ],
                'scores' => [
                    'total_score' => $testResult->total_score,
                    'average_score' => $testResult->average_score,
                    'average_percentage' => round($this->convertToPercentage($testResult->average_score ?? 0), 0), // Rounded to whole number
                    'cluster_scores' => $testResult->cluster_scores,
                    'construct_scores' => $testResult->construct_scores,
                ],
                'radar_chart' => $radarChartData,
                'status' => $testResult->status,
                'submitted_at' => $testResult->created_at,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $formattedResults,
            'message' => 'Test results fetched successfully'
        ], 200);
    }

    /**
     * Get all test results for all users with comprehensive data
     * Includes: user details, tests, questions, answers, scores, clusters, constructs with percentages
     */
    public function getAllTestResultsComprehensive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $testResults = $this->fetchTestResultsWithRelations($fromDate, $toDate);
        $formattedResults = $this->transformTestResults($testResults);

        return response()->json([
            'status' => true,
            'data' => $formattedResults,
            'total_results' => $formattedResults->count(),
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'message' => 'All test results fetched successfully'
        ], 200);
    }

    /**
     * Download comprehensive test results as an Excel file with multiple sheets.
     */
    public function downloadTestResultsExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $testResults = $this->fetchTestResultsWithRelations($fromDate, $toDate);
        $formattedResults = $this->transformTestResults($testResults);

        $filteredResults = $formattedResults->filter(function ($result) {
            return strtolower($result['user']['role'] ?? '') === 'user';
        })->values();

        $fileName = 'user-test-results-' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new UserTestDataExport(
                $this->buildExportDatasets($filteredResults)['raw']
            ),
            $fileName
        );
    }

    /**
     * Fetch test results with required relationships and optional date filters.
     */
    protected function fetchTestResultsWithRelations(?string $fromDate, ?string $toDate): Collection
    {
        $query = TestResult::with([
            'user',
            'test',
            'answers.question.construct.cluster',
            'test.selectedQuestions',
        ])->orderByDesc('created_at');

        if ($fromDate) {
            $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        return $query->get();
    }

    /**
     * Transform test results into structured data for API/export responses.
     */
    protected function transformTestResults(Collection $testResults): Collection
    {
        $options = OptionsModel::orderBy('value')->get()->keyBy('value');

        return $testResults->map(function ($testResult) use ($options) {
            if (!$testResult->user || !$testResult->test) {
                return null;
            }

            $questionOrder = $testResult->test->selectedQuestions
                ? $testResult->test->selectedQuestions->pluck('pivot.order_no', 'id')->toArray()
                : [];

            $questionsWithAnswers = $testResult->answers->map(function ($answer) use ($options, $questionOrder) {
                $question = $answer->question;
                if (!$question) {
                    return null;
                }

                $optionLabel = $options->get($answer->answer_value);

                return [
                    'question_id' => $question->id,
                    'question_text' => $question->question_text,
                    'category' => $question->category,
                    'order_no' => $questionOrder[$question->id] ?? null,
                    'construct' => $question->construct ? [
                        'id' => $question->construct->id,
                        'name' => $question->construct->name,
                        'cluster' => $question->construct->cluster ? [
                            'id' => $question->construct->cluster->id,
                            'name' => $question->construct->cluster->name,
                        ] : null,
                    ] : null,
                    'answer' => [
                        'answer_value' => $answer->answer_value,
                        'answer_label' => $optionLabel ? $optionLabel->label : null,
                        'final_score' => $answer->final_score,
                    ],
                ];
            })->filter()->sortBy(function ($item) {
                return $item['order_no'] ?? $item['question_id'];
            })->values();

            $clusterScores = $this->transformScoreCollection($testResult->cluster_scores);
            $constructScores = $this->transformScoreCollection($testResult->construct_scores);
            $overallPercentage = $this->calculatePercentageFromMean($testResult->average_score ?? 0);

            return [
                'test_result_id' => $testResult->id,
                'user' => [
                    'id' => $testResult->user->id,
                    'name' => $testResult->user->name,
                    'email' => $testResult->user->email,
                    'first_name' => $testResult->user->first_name,
                    'last_name' => $testResult->user->last_name,
                    'role' => $testResult->user->role,
                    'contact_number' => $testResult->user->contact_number,
                    'whatsapp_number' => $testResult->user->whatsapp_number,
                    'gender' => $testResult->user->gender,
                    'age' => $testResult->user->age,
                    'city' => $testResult->user->city,
                    'state' => $testResult->user->state,
                    'country' => $testResult->user->country,
                    'profession' => $testResult->user->profession,
                    'educational_qualification' => $testResult->user->educational_qualification,
                ],
                'test' => [
                    'id' => $testResult->test->id,
                    'title' => $testResult->test->title,
                    'description' => $testResult->test->description,
                ],
                'scores' => [
                    'total_score' => $testResult->total_score,
                    'average_score' => $testResult->average_score,
                    'average_percentage' => $overallPercentage,
                    'overall_category' => $testResult->overall_category ?? $this->categorizeByPercentage($overallPercentage),
                ],
                'questions' => $questionsWithAnswers,
                'clusters' => $clusterScores,
                'constructs' => $constructScores,
                'sdb' => [
                    'raw_score' => $testResult->sdb_raw_score,
                    'percentage' => $testResult->sdb_percentage,
                    'band' => $testResult->sdb_band,
                    'flag' => $testResult->sdb_flag,
                ],
                'status' => $testResult->status,
                'submitted_at' => optional($testResult->created_at)->toDateTimeString(),
                'updated_at' => optional($testResult->updated_at)->toDateTimeString(),
            ];
        })->filter()->values();
    }

    /**
     * Build datasets for Excel export (raw data + summaries).
     */
    protected function buildExportDatasets(Collection $results): array
    {
        $userHeaders    = $this->getUserHeaderLabels();
        $questionCols   = $this->buildQuestionColumnsMeta($results);
        $clusterNames   = $this->collectSummaryNames($results, 'clusters');
        $constructNames = $this->collectSummaryNames($results, 'constructs');

        $rawRows = $this->buildRawDataRows(
            $results,
            $userHeaders,
            $questionCols,
            $clusterNames,
            $constructNames
        );

        return [
            'raw' => $rawRows,
        ];
    }

    /**
     * Transform cluster/construct score structures into a normalized array.
     */
    private function transformScoreCollection($scores): array
    {
        if (empty($scores) || (!is_array($scores) && !($scores instanceof \Traversable))) {
            return [];
        }

        $formatted = [];

        foreach ($scores as $name => $data) {
            if (is_array($data)) {
                $meanScore = $data['average'] ?? 0;
                $percentage = $this->calculatePercentageFromMean($meanScore);

                $formatted[$name] = [
                    'name' => $name,
                    'total' => $data['total'] ?? null,
                    'average' => $meanScore,
                    'percentage' => $percentage,
                    'count' => $data['count'] ?? null,
                    'category' => $data['category'] ?? $this->categorizeByPercentage($percentage),
                ];
            } else {
                $meanScore = (float) $data;
                $percentage = $this->calculatePercentageFromMean($meanScore);

                $formatted[$name] = [
                    'name' => $name,
                    'total' => null,
                    'average' => $meanScore,
                    'percentage' => $percentage,
                    'count' => null,
                    'category' => $this->categorizeByPercentage($percentage),
                ];
            }
        }

        return $formatted;
    }

    /**
     * Get canonical list of user info headers for Excel exports.
     */
    protected function getUserHeaderLabels(): array
    {
        return [
            'name',
            'email',
            'contact_number',
            'whatsapp_number',
            'gender',
            'age',
            'city',
            'state',
            'country',
            'profession',
            'educational_qualification',
        ];
    }

    /**
     * Build the raw data sheet rows (with questions, plus cluster/construct scores at the end).
     *
     * @param  array<int, array<string, mixed>>  $questionColumns
     * @param  array<int, string>                $clusterNames
     * @param  array<int, string>                $constructNames
     */
    protected function buildRawDataRows(
        Collection $results,
        array $userHeaders,
        array $questionColumns,
        array $clusterNames,
        array $constructNames
    ): array
    {
        $questionCount     = count($questionColumns);
        $userColumnCount   = count($userHeaders);
        $clusterCount      = count($clusterNames);
        $constructCount    = count($constructNames);
        // One extra summary column for SDB (raw score)
        $sdbColumnCount    = 1;
        $summaryColsCount  = $clusterCount + $constructCount + $sdbColumnCount;
        $rows = [];

        $clusterHeader = array_merge(
            array_fill(0, $userColumnCount, null),
            array_map(fn ($column) => 'Cluster : ' . $column['cluster'], $questionColumns)
        );

        $constructHeader = array_merge(
            array_fill(0, $userColumnCount, null),
            array_map(fn ($column) => 'Construct: ' . $column['construct'], $questionColumns)
        );

        $questionHeader = array_merge(
            $this->formatHeaderLabels($userHeaders),
            array_map(fn ($column) => $column['label'], $questionColumns)
        );

        // Extra headings for summary scores at the end
        $clusterSummaryHeading = [];
        if ($clusterCount > 0) {
            $clusterSummaryHeading[] = 'Clusters';
            for ($i = 1; $i < $clusterCount; $i++) {
                $clusterSummaryHeading[] = null;
            }
        }

        $constructSummaryHeading = [];
        if ($constructCount > 0) {
            $constructSummaryHeading[] = 'Constructs';
            for ($i = 1; $i < $constructCount; $i++) {
                $constructSummaryHeading[] = null;
            }
        }

        // 1) Cluster row over question columns, then "Clusters"/"Constructs"/"SDB" headings at the far right
        $rows[] = array_merge(
            $clusterHeader,
            $clusterSummaryHeading,
            $constructSummaryHeading,
            ['SDB']
        );

        // 2) Construct row over question columns, then each cluster/construct name as column labels (SDB column is empty on this row)
        $rows[] = array_merge(
            $constructHeader,
            $clusterNames,
            $constructNames,
            [null]
        );

        // 3) Question labels row, with empty cells in the summary section (including SDB column)
        $rows[] = array_merge(
            $questionHeader,
            array_fill(0, $summaryColsCount, null)
        );

        foreach ($results as $result) {
            $userRow = $this->formatUserInfoValues($result['user'] ?? [], $userHeaders);
            $questionMap = $this->indexQuestionsById($result['questions'] ?? []);

            $scoreRow = [];

            foreach ($questionColumns as $column) {
                $question = $questionMap[$column['question_id']] ?? null;
                $scoreRow[] = data_get($question, 'answer.final_score');
            }

            // Summary scores for this user
            $clusterValues = [];
            $clusterItems  = data_get($result, 'clusters', []);
            foreach ($clusterNames as $name) {
                $entry = $clusterItems[$name] ?? null;
                $clusterValues[] = $entry ? $this->extractPercentageScore($entry) : null;
            }

            $constructValues = [];
            $constructItems  = data_get($result, 'constructs', []);
            foreach ($constructNames as $name) {
                $entry = $constructItems[$name] ?? null;
                $constructValues[] = $entry ? $this->extractPercentageScore($entry) : null;
            }

            // SDB raw score (average of 18 items) – no percentage
            $sdbRawScore = data_get($result, 'sdb.raw_score');

            // User info + scores + summary scores (cluster, construct, SDB)
            $rows[] = array_merge(
                $userRow,
                $scoreRow,
                $clusterValues,
                $constructValues,
                [$sdbRawScore]
            );

            // Blank separator row
            $rows[] = array_fill(0, $userColumnCount + $questionCount + $summaryColsCount, null);
        }

        return $rows;
    }

    /**
     * Build cluster / construct summary sheet rows.
     *
     * @param  array<int, string>  $names
     */
    protected function buildSummaryRows(Collection $results, array $userHeaders, array $names, string $key): array
    {
        $columnCount = count($userHeaders) + count($names);
        $rows = [];

        $rows[] = array_fill(0, $columnCount, null);
        $rows[] = array_merge($this->formatHeaderLabels($userHeaders), $names);

        foreach ($results as $result) {
            $userRow = $this->formatUserInfoValues($result['user'] ?? [], $userHeaders);
            $summaryItems = data_get($result, $key, []);

            $valueRow = [];
            foreach ($names as $name) {
                $entry = $summaryItems[$name] ?? null;
                if (!$entry) {
                    $valueRow[] = null;
                    continue;
                }

                $valueRow[] = $this->extractPercentageScore($entry);
            }

            $rows[] = array_merge($userRow, $valueRow);
            $rows[] = array_fill(0, $columnCount, null);
        }

        return $rows;
    }

    /**
     * Collect unique ordered names for cluster/construct summary columns.
     */
    protected function collectSummaryNames(Collection $results, string $key): array
    {
        $ordered = [];

        foreach ($results as $result) {
            $items = data_get($result, $key, []);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $name => $data) {
                if ($name === '' || $name === null) {
                    continue;
                }
                if (!array_key_exists($name, $ordered)) {
                    $ordered[$name] = count($ordered);
                }
            }
        }

        asort($ordered);
        return array_keys($ordered);
    }

    /**
     * Build ordered question columns with cluster / construct context.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildQuestionColumnsMeta(Collection $results): array
    {
        $clusters = [];

        foreach ($results as $result) {
            foreach ($result['questions'] ?? [] as $question) {
                $clusterName = data_get($question, 'construct.cluster.name', 'N/A');
                $constructName = data_get($question, 'construct.name', 'N/A');
                $questionId = $question['question_id'] ?? null;

                if (!$questionId) {
                    continue;
                }

                if (!isset($clusters[$clusterName])) {
                    $clusters[$clusterName] = [
                        'order' => count($clusters),
                        'constructs' => [],
                    ];
                }

                if (!isset($clusters[$clusterName]['constructs'][$constructName])) {
                    $clusters[$clusterName]['constructs'][$constructName] = [
                        'order' => count($clusters[$clusterName]['constructs']),
                        'questions' => [],
                    ];
                }

                $clusters[$clusterName]['constructs'][$constructName]['questions'][$questionId] = [
                    'question_id' => $questionId,
                    'order' => $question['order_no'] ?? $questionId,
                    'category' => $this->formatCategoryCode($question['category'] ?? null),
                    'question_text' => $question['question_text'] ?? null,
                ];
            }
        }

        if (empty($clusters)) {
            return [];
        }

        $columns = [];
        $clusterPositions = array_map(fn ($data) => $data['order'], $clusters);
        asort($clusterPositions);

        foreach (array_keys($clusterPositions) as $clusterName) {
            $constructs = $clusters[$clusterName]['constructs'] ?? [];
            $constructPositions = array_map(fn ($data) => $data['order'], $constructs);
            asort($constructPositions);

            foreach (array_keys($constructPositions) as $constructName) {
                $questions = $constructs[$constructName]['questions'] ?? [];
                uasort($questions, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

                foreach ($questions as $meta) {
                    $labelQuestionText = $meta['question_text'] ?? '';
                    $labelCategory = $meta['category'] ?? '';

                    $columns[] = [
                        'cluster' => $clusterName,
                        'construct' => $constructName,
                        'question_id' => $meta['question_id'],
                        'question_text' => $meta['question_text'],
                        // Header format: "I want to win (P)"
                        'label' => trim(sprintf('%s (%s)', $labelQuestionText, $labelCategory)),
                    ];
                }
            }
        }

        return $columns;
    }

    /**
     * Helper to index question entries by their id.
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  iterable<int, array<string, mixed>>|array<int, array<string, mixed>>  $questions
     */
    protected function indexQuestionsById(iterable $questions): array
    {
        $indexed = [];

        foreach ($questions as $question) {
            if (!isset($question['question_id'])) {
                continue;
            }
            $indexed[$question['question_id']] = $question;
        }

        return $indexed;
    }

    /**
     * Format user info row values based on header order.
     */
    protected function formatUserInfoValues(array $user, array $headers): array
    {
        return array_map(
            fn ($field) => $user[$field] ?? null,
            $headers
        );
    }

    /**
     * Convert header keys into human-readable labels.
     */
    protected function formatHeaderLabels(array $headers): array
    {
        return array_map(function ($label) {
            return ucwords(str_replace('_', ' ', $label));
        }, $headers);
    }

    /**
     * Normalize question category codes (P, R, SDB, etc).
     */
    protected function formatCategoryCode(?string $category): string
    {
        $category = strtoupper((string) $category);

        return match ($category) {
            'P', 'POSITIVE' => 'P',
            'R', 'REVERSE' => 'R',
            'SDB' => 'SDB',
            default => 'GEN',
        };
    }

    /**
     * Resolve percentage score for a cluster/construct entry.
     */
    protected function extractPercentageScore(array $entry): ?float
    {
        $percentage = $entry['percentage'] ?? null;

        if ($percentage === null && isset($entry['average'])) {
            $percentage = $this->calculatePercentageFromMean($entry['average']);
        }

        return $percentage !== null ? round((float) $percentage, 0) : null;
    }

    /**
     * Calculate percentage from mean score using formula: ((mean - 1) / 4) * 100
     * Example: 3.57 -> ((3.57 - 1) / 4) * 100 = 64.25% -> 64% (rounded)
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
     * Categorize score by percentage: 0-59 = Low, 60-79 = Medium, 80-100 = High
     */
    private function categorizeByPercentage($percentage)
    {
        if ($percentage < 60) {
            return 'Low';
        } elseif ($percentage < 80) {
            return 'Medium';
        } else {
            return 'High';
        }
    }
}
