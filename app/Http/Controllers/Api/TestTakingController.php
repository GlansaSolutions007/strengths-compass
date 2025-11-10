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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

            // Calculate cluster and construct scores
            $clusterScores = $this->calculateClusterScores($userAnswers, $test);
            $constructScores = $this->calculateConstructScores($userAnswers, $test);

            // Check for SDB flag (if too many SDB questions have high scores)
            $sdbFlag = $this->checkSDBFlag($userAnswers);

            // Update test result with calculated scores
            $testResult->update([
                'total_score' => $totalScore,
                'average_score' => $averageScore,
                'cluster_scores' => $clusterScores,
                'construct_scores' => $constructScores,
                'sdb_flag' => $sdbFlag
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Test submitted successfully',
                'data' => [
                    'test_result_id' => $testResult->id,
                    'total_score' => round($totalScore, 2),
                    'average_score' => round($averageScore, 2),
                    'cluster_scores' => $clusterScores,
                    'construct_scores' => $constructScores,
                    'sdb_flag' => $sdbFlag,
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
     * Calculate cluster scores
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

        // Calculate average scores per cluster
        foreach ($clusterTotals as $clusterId => $total) {
            $count = $clusterCounts[$clusterId];
            $average = $count > 0 ? $total / $count : 0;
            
            $cluster = $test->clusters->find($clusterId);
            $clusterName = $cluster ? $cluster->name : "Cluster {$clusterId}";
            
            $clusterScores[$clusterName] = round($average, 2);
        }

        return $clusterScores;
    }

    /**
     * Calculate construct scores
     */
    private function calculateConstructScores($userAnswers, $test)
    {
        $constructScores = [];
        $constructTotals = [];
        $constructCounts = [];

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
            }

            $constructTotals[$constructId] += $answer['final_score'];
            $constructCounts[$constructId]++;
        }

        // Calculate average scores per construct
        foreach ($constructTotals as $constructId => $total) {
            $count = $constructCounts[$constructId];
            $average = $count > 0 ? $total / $count : 0;
            
            // Get construct name from any question in this construct
            $constructName = "Construct {$constructId}";
            foreach ($userAnswers as $answer) {
                $question = QuestionsModel::with('construct')->find($answer['question_id']);
                if ($question && $question->construct && $question->construct_id == $constructId) {
                    $constructName = $question->construct->name;
                    break;
                }
            }
            
            $constructScores[$constructName] = round($average, 2);
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
     * Get test results for a user
     */
    public function getResults($testResultId)
    {
        $testResult = TestResult::with(['test', 'user', 'answers.question.construct.cluster'])
            ->find($testResultId);

        if (!$testResult) {
            return response()->json([
                'status' => false,
                'message' => 'Test result not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $testResult,
            'message' => 'Test result fetched successfully'
        ], 200);
    }

    /**
     * Get all test results for a user
     */
    public function getUserResults($userId)
    {
        $testResults = TestResult::with(['test'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $testResults,
            'message' => 'User test results fetched successfully'
        ], 200);
    }

    /**
     * Get all test results for a specific test
     */
    public function getTestResults($testId)
    {
        $testResults = TestResult::with(['user'])
            ->where('test_id', $testId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $testResults,
            'message' => 'Test results fetched successfully'
        ], 200);
    }
}
