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
use App\Mail\TestCompletionMail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
                $includeInConstruct = $scoringRule->include_in_construct ?? true;

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

            // Calculate cluster and construct scores
            $clusterScores = $this->calculateClusterScores($userAnswers, $test);
            $constructScores = $this->calculateConstructScores($userAnswers, $test);

            // Check for SDB flag (if too many SDB questions have high scores)
            $sdbFlag = $this->checkSDBFlag($userAnswers);

            // Calculate overall category based on average score
            $overallCategory = $this->categorizeScore($averageScore);

            // Update test result with calculated scores
            $testResult->update([
                'total_score' => $totalScore,
                'average_score' => $averageScore,
                'overall_category' => $overallCategory,
                'cluster_scores' => $clusterScores,
                'construct_scores' => $constructScores,
                'sdb_flag' => $sdbFlag
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
                    'overall_category' => $overallCategory,
                    'cluster_scores' => $clusterScores,
                    'construct_scores' => $constructScores,
                    'sdb_flag' => $sdbFlag,
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
     * Categorize score: 1-2 = low, 3 = medium, 4-5 = high
     */
    private function categorizeScore($score)
    {
        if ($score <= 2) {
            return 'low';
        } elseif ($score == 3) {
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
            
            $cluster = $test->clusters->find($clusterId);
            $clusterName = $cluster ? $cluster->name : "Cluster {$clusterId}";
            
            $clusterScores[$clusterName] = [
                'total' => round($total, 2),
                'average' => $average,
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
            $constructName = $constructNames[$constructId] ?? "Construct {$constructId}";
            
            $constructScores[$constructName] = [
                'total' => round($total, 2),
                'average' => $average,
                'count' => $count,
                'category' => $this->categorizeScore($average)
            ];
        }

        return $constructScores;
    }

    /**
     * Check for Social Desirability Bias flag
     * If too many SDB questions have high scores (4 or 5), flag it
     */
    private function checkSDBFlag($userAnswers)
    {
        $sdbAnswers = array_filter($userAnswers, function ($answer) {
            return $answer['category'] === 'SDB';
        });

        if (count($sdbAnswers) === 0) {
            return false;
        }

        $highScoreCount = 0;
        foreach ($sdbAnswers as $answer) {
            if ($answer['answer_value'] >= 4) {
                $highScoreCount++;
            }
        }

        // Flag if more than 70% of SDB questions have high scores
        $threshold = count($sdbAnswers) * 0.7;
        return $highScoreCount > $threshold;
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
                    'overall_category' => $testResult->overall_category ?? $this->categorizeScore($testResult->average_score ?? 0),
                    'cluster_scores' => $testResult->cluster_scores,
                    'construct_scores' => $testResult->construct_scores,
                    'sdb_flag' => $testResult->sdb_flag,
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
}
