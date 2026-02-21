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
use App\Models\Cluster;
use App\Models\User;
use App\Models\Language;
use App\Models\QuestionTranslation;
use App\Exports\UserTestDataExport;
use App\Mail\TestCompletionMail;
use App\Mail\TestSubmissionAdminMail;
use App\Jobs\SendTestCompletionEmails;
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
     * Supports language translations via 'lang' parameter (default: 'en')
     * If translation exists, returns translated_text; otherwise returns question_text
     */
    public function getTestForUser(Request $request, $testId)
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

        // For CERC tests, validate that user has completed SC Pro test first and filter questions
        $selectedQuestions = $test->selectedQuestions;
        $scProTest = null;
        $scProTestResult = null;
        $scProAnswersByQuestionId = []; // question_id => answer map for CERC (give ans)

        if ($test->source === 'CERC') {
            $userId = $request->input('user_id');
            
            // user_id is optional for CERC - user can take test directly without prior SC Pro completion
            // But if provided AND user has completed SC Pro, check for SC Pro answers to pre-fill/filter
            if ($userId) {
                // Find SC Pro test - use explicit mapping if available, otherwise fallback to age group
                if ($test->sc_pro_test_id) {
                    // Use explicit mapping
                    $scProTest = Test::where('id', $test->sc_pro_test_id)
                        ->where('source', 'SC Pro')
                        ->where('is_active', true)
                        ->first();
                } else {
                    // Fallback: Find SC Pro test for same age group (backward compatibility)
                    $scProTest = Test::where('source', 'SC Pro')
                        ->where('is_active', true)
                        ->where('age_group_id', $test->age_group_id)
                        ->first();
                }
                
                // Check if user has completed SC Pro test (if SC Pro test exists)
                if ($scProTest) {
                    $scProTestResult = TestResult::where('user_id', $userId)
                        ->where('test_id', $scProTest->id)
                        ->where('status', 'completed')
                        ->latest()
                        ->first();
                    
                    // If user has completed SC Pro, filter and pre-fill CERC answers
                    // Otherwise, show all CERC questions (user taking CERC directly without SC Pro)
                    if ($scProTestResult) {
                        // CERC uses same question IDs as SC Pro (existing questions reused). Map and filter by question_id only.
                        $scProAnswers = UserAnswer::where('test_result_id', $scProTestResult->id)->get();
                        $answeredQuestionIds = $scProAnswers->pluck('question_id')->unique()->values()->toArray();

                        // Map question_id => answer for pre-fill (give ans): frontend can show SC Pro answers for same question IDs
                        $scProAnswersByQuestionId = [];
                        foreach ($scProAnswers as $ua) {
                            $scProAnswersByQuestionId[$ua->question_id] = [
                                'question_id' => $ua->question_id,
                                'answer_value' => $ua->answer_value,
                                'final_score' => $ua->final_score ?? null,
                            ];
                        }

                        // Filter out questions already answered in SC Pro (by question_id only)
                        if (!empty($answeredQuestionIds)) {
                            $originalCount = $selectedQuestions->count();
                            $selectedQuestions = $selectedQuestions->filter(function ($question) use ($answeredQuestionIds) {
                                return !in_array($question->id, $answeredQuestionIds);
                            })->values();
                            \Log::info('CERC Test Question Filtering (by question_id only)', [
                                'cerc_test_id' => $test->id,
                                'sc_pro_test_id' => $scProTest->id,
                                'user_id' => $userId,
                                'answered_question_ids' => $answeredQuestionIds,
                                'original_count' => $originalCount,
                                'filtered_count' => $selectedQuestions->count(),
                            ]);
                        }
                    }
                }
            }
        }

        // Get all options (same for every question) and format them
        $options = OptionsModel::orderBy('value')->get()->map(function ($option) {
            return [
                'id' => $option->id,
                'label' => $option->label,
                'value' => $option->value,
            ];
        })->values();

        // Handle language translation
        $lang = $request->input('lang', 'en');
        $languageId = null;

        // Get language ID if not English
        if ($lang !== 'en') {
            $language = Language::where(function($query) use ($lang) {
                $query->whereRaw('LOWER(name) = ?', [strtolower(trim($lang))])
                      ->orWhere('code', trim($lang));
            })
            ->where('is_active', true)
            ->first();

            if ($language) {
                $languageId = $language->id;
            }
        }

        // If language is specified and found, load translations
        $translations = [];
        if ($languageId) {
            $questionIds = $selectedQuestions->pluck('id')->toArray();
            $translations = QuestionTranslation::whereIn('question_id', $questionIds)
                ->where('language_id', $languageId)
                ->where('is_active', true)
                ->pluck('translated_text', 'question_id')
                ->map(function ($text) {
                    // Ensure UTF-8 encoding
                    if (!mb_check_encoding($text, 'UTF-8')) {
                        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
                    }
                    return $text;
                })
                ->toArray();
        }

        // Format questions with their order
        $questions = $selectedQuestions->map(function ($question) use ($translations) {
            $questionData = [
                'id' => $question->id,
                'question_text' => $question->question_text, // Default to English
                'category' => $question->category,
                'order_no' => $question->pivot->order_no,
                'construct_id' => $question->construct_id,
                'construct_name' => $question->construct->name ?? null,
                'cluster_id' => $question->pivot->cluster_id ?? null,
            ];

            // Replace with translated text if available
            if (isset($translations[$question->id])) {
                $translatedText = $translations[$question->id];
                // Ensure UTF-8 encoding
                if (!mb_check_encoding($translatedText, 'UTF-8')) {
                    $translatedText = mb_convert_encoding($translatedText, 'UTF-8', 'UTF-8');
                }
                $questionData['question_text'] = $translatedText;
                $questionData['is_translated'] = true;
            } else {
                $questionData['is_translated'] = false;
            }

            return $questionData;
        })->sortBy('order_no')->values();

        // For SC Pro tests, include linked CERC test info
        $cercTestInfo = null;
        if ($test->source === 'SC Pro') {
            $cercTests = Test::where('sc_pro_test_id', $test->id)
                ->where('source', 'CERC')
                ->where('is_active', true)
                ->get();
            
            if ($cercTests->count() > 0) {
                $cercTestInfo = $cercTests->map(function ($cercTest) {
                    return [
                        'id' => $cercTest->id,
                        'title' => $cercTest->title,
                        'description' => $cercTest->description,
                        'source' => $cercTest->source,
                    ];
                })->values();
            }
        }

        // Track if questions were filtered (for CERC tests)
        $questionsFiltered = ($test->source === 'CERC' && $scProTestResult !== null);

        $responseData = [
            'test' => [
                'id' => $test->id,
                'title' => $test->title,
                'description' => $test->description,
                'source' => $test->source ?? 'SC Pro',
            ],
            'questions' => $questions,
            'options' => $options,
            'total_questions' => $questions->count(),
            'questions_filtered' => $questionsFiltered,
        ];

        // CERC: map of question_id => SC Pro answer (for pre-fill / give ans)
        if ($test->source === 'CERC' && !empty($scProAnswersByQuestionId)) {
            $responseData['sc_pro_answers_by_question_id'] = $scProAnswersByQuestionId;
        }

        if ($cercTestInfo !== null) {
            $responseData['available_cerc_tests'] = $cercTestInfo;
        }

        return response()->json([
            'status' => true,
            'data' => $responseData,
            'message' => 'Test fetched successfully',
            'language' => $lang,
        ], 200);
    }

    /**
     * Submit test answers and calculate scores
     */
    public function submitAnswers(Request $request, $testId)
    {
        // 1️⃣ Verify Test
        $test = $this->validateTest($testId);
        if ($test instanceof \Illuminate\Http\JsonResponse) {
            return $test;
        }

        // 2️⃣ Validate Request
        $validationResult = $this->validateTestSubmission($request, $test);
        if ($validationResult instanceof \Illuminate\Http\JsonResponse) {
            return $validationResult;
        }

        $userId = $request->input('user_id');
        $answers = $request->input('answers');
        $isConsent = $request->boolean('is_consent', false);

        // 3️⃣ Combine answers (for CERC tests, merge with SC Pro answers)
        $combinedAnswers = $this->combineAnswersForTest($test, $userId, $answers);

        $result = $this->processAndSaveTestAnswers($test, $testId, $userId, $isConsent, $combinedAnswers);
        
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return response()->json([
            'status' => true,
            'message' => 'Test submitted successfully',
            'data' => $result
        ], 201);
    }

    /**
     * Validate and get test
     */
    private function validateTest($testId)
    {
        $test = Test::with('selectedQuestions')->find($testId);
        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }
        return $test;
    }

    /**
     * Validate test submission request
     */
    private function validateTestSubmission($request, $test, $testId = null)
    {
        $testId = $testId ?? $test->id;
        $validQuestionIds = $test->selectedQuestions->pluck('id')->toArray();

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'is_consent' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
                        $fail('You must provide consent to take this assessment. Please check the consent checkbox.');
                    }
                },
            ],
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => [
                'required',
                'integer',
                function ($attr, $value, $fail) use ($validQuestionIds, $testId) {
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

        return null;
    }

    /**
     * Combine SC Pro and CERC answers for CERC tests
     */
    private function combineAnswersForTest($test, $userId, $answers)
    {
        if ($test->source !== 'CERC') {
            return $answers;
        }

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

        if (!$scProTest) {
            return $answers;
        }

        $scProTestResult = TestResult::where('user_id', $userId)
            ->where('test_id', $scProTest->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (!$scProTestResult) {
            return $answers;
        }

        // CERC uses same question IDs as SC Pro. Map by question_id only.
        $scProAnswers = UserAnswer::where('test_result_id', $scProTestResult->id)->get();
        $scProAnswersById = $scProAnswers->keyBy('question_id');

        $cercTestQuestionIds = $test->selectedQuestions->pluck('id')->toArray();
        $combinedAnswers = [];
        $usedScProQuestionIds = [];

        foreach ($cercTestQuestionIds as $cercQuestionId) {
            if (isset($scProAnswersById[$cercQuestionId])) {
                $scProAnswer = $scProAnswersById[$cercQuestionId];
                $combinedAnswers[] = [
                    'question_id' => $cercQuestionId,
                    'answer_value' => $scProAnswer->answer_value,
                    'final_score' => $scProAnswer->final_score,
                    'from_sc_pro' => true,
                ];
                $usedScProQuestionIds[] = $cercQuestionId;
            }
        }

        // Add CERC answers (questions not answered in SC Pro)
        foreach ($answers as $cercAnswer) {
            if (!in_array($cercAnswer['question_id'], $usedScProQuestionIds)) {
                $cercAnswer['from_sc_pro'] = false;
                $combinedAnswers[] = $cercAnswer;
            }
        }

        return $combinedAnswers;
    }

    /**
     * Process and save test answers
     */
    private function processAndSaveTestAnswers($test, $testId, $userId, $isConsent, $combinedAnswers)
    {
        $questionIds = collect($combinedAnswers)->pluck('question_id')->unique();
        $questions = QuestionsModel::with('construct.cluster')
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy('id');
        $scoringRules = ScoringRule::whereIn('question_id', $questionIds)
            ->get()
            ->keyBy('question_id');

        DB::beginTransaction();
        try {
            $testResult = TestResult::create([
                'user_id' => $userId,
                'test_id' => $testId,
                'status' => 'completed',
                'is_consent' => $isConsent
            ]);

            $processedData = $this->processAnswers($test, $testResult, $combinedAnswers, $questions, $scoringRules);
            DB::table('user_answers')->insert($processedData['answerRows']);

            $scores = $this->calculateAllScores($processedData['userAnswersForCalc'], $test);
            $testResult->update($scores);
            DB::commit();

            dispatch(new SendTestCompletionEmails($testResult->id, $userId, $testId));

            $responseData = [
                'test_result_id' => $testResult->id,
                'total_score' => $scores['total_score'],
                'average_score' => $scores['average_score'],
                'average_percentage' => round($this->convertToPercentage($scores['average_score']), 0),
                'overall_category' => $scores['overall_category'],
                'cluster_scores' => $scores['cluster_scores'],
                'construct_scores' => $scores['construct_scores'],
                'sdb_flag' => $scores['sdb_flag'],
                'radar_chart' => $this->formatRadarChartData($scores['cluster_scores']),
                'total_questions_answered' => count($processedData['answerRows'])
            ];

            if ($test->source === 'SC Pro') {
                $cercTests = Test::where('sc_pro_test_id', $test->id)
                    ->where('source', 'CERC')
                    ->where('is_active', true)
                    ->get();
                
                if ($cercTests->count() > 0) {
                    $responseData['available_cerc_tests'] = $cercTests->map(function ($cercTest) {
                        return [
                            'id' => $cercTest->id,
                            'title' => $cercTest->title,
                            'description' => $cercTest->description,
                            'source' => $cercTest->source,
                        ];
                    })->values();
                    $responseData['has_cerc_tests'] = true;
                } else {
                    $responseData['has_cerc_tests'] = false;
                }
            } else {
                $responseData['has_cerc_tests'] = false;
            }

            return $responseData;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Test submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error submitting test: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process answers and prepare for insertion
     */
    private function processAnswers($test, $testResult, $combinedAnswers, $questions, $scoringRules)
    {
        $answerRows = [];
        $userAnswersForCalc = [];
        $totalScore = 0;
        $questionCount = 0;

        foreach ($combinedAnswers as $answer) {
            $isFromScPro = isset($answer['from_sc_pro']) && $answer['from_sc_pro'] === true;
            $question = $questions[$answer['question_id']] ?? null;
            if (!$question) continue;

            $rule = $scoringRules[$answer['question_id']] ?? null;
            $category = $rule->category ?? $question->category;
            $reverse = $rule->reverse_score ?? false;
            $weight = $rule->weight ?? 1.0;
            $includeInConstruct = (strtoupper($category) === 'SDB') ? false : ($rule->include_in_construct ?? true);

            $finalScore = ($isFromScPro && isset($answer['final_score'])) 
                ? $answer['final_score'] 
                : round($this->calculateScore($answer['answer_value'], $category, $reverse, $weight), 2);

            if ($test->source !== 'CERC' || !$isFromScPro) {
                $answerRows[] = [
                    'test_result_id' => $testResult->id,
                    'question_id' => $answer['question_id'],
                    'answer_value' => $answer['answer_value'],
                    'final_score' => $finalScore,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $userAnswersForCalc[] = [
                'question_id' => $answer['question_id'],
                'answer_value' => $answer['answer_value'],
                'final_score' => $finalScore,
                'category' => $category,
                'include_in_construct' => $includeInConstruct
            ];

            if ($includeInConstruct) {
                $totalScore += $finalScore;
                $questionCount++;
            }
        }

        return [
            'answerRows' => $answerRows,
            'userAnswersForCalc' => $userAnswersForCalc,
            'totalScore' => $totalScore,
            'questionCount' => $questionCount
        ];
    }

    /**
     * Calculate all scores for test result
     */
    private function calculateAllScores($userAnswersForCalc, $test)
    {
        $totalScore = 0;
        $questionCount = 0;
        
        foreach ($userAnswersForCalc as $answer) {
            if ($answer['include_in_construct'] ?? false) {
                $totalScore += $answer['final_score'];
                $questionCount++;
            }
        }
        
        $averageScore = $questionCount > 0 ? round($totalScore / $questionCount, 2) : 0;
        $totalScore = round($totalScore, 2);

        return [
            'total_score' => $totalScore,
            'average_score' => $averageScore,
            'overall_category' => $this->categorizeScore($averageScore),
            'cluster_scores' => $this->calculateClusterScores($userAnswersForCalc, $test),
            'construct_scores' => $this->calculateConstructScores($userAnswersForCalc, $test),
            'sdb_flag' => $this->checkSDBFlag($userAnswersForCalc),
        ];
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
     * Categorize based on percentage: 0-59 = low, 60-75 = medium, 76-100 = high
     */
    private function categorizeScore($score)
    {
        // Convert to percentage first
        $percentage = $this->convertToPercentage($score);
        
        if ($percentage < 60) {
            return 'low';
        } elseif ($percentage < 76) {
            return 'medium';
        } else {
            return 'high';
        }
    }

    /**
     * Normalize question text for comparison (trim whitespace and convert to lowercase)
     * Used to match questions with same text but different IDs
     */
    private function normalizeQuestionText($questionText)
    {
        if (empty($questionText)) {
            return '';
        }
        
        // Trim whitespace and normalize to lowercase for case-insensitive comparison
        return mb_strtolower(trim($questionText), 'UTF-8');
    }

    /**
     * Calculate cluster scores (both totals and averages).
     * Uses test_question.cluster_id (test-specific) so same construct can be in different clusters per test.
     */
private function calculateClusterScores($userAnswers, $test)
    {
        $clusterScores = [];
        $clusterTotals = [];
        $clusterCounts = [];

        // Map question_id -> cluster_id for this test (from test_question pivot)
        $test->load('selectedQuestions');
        $questionIdToClusterId = $test->selectedQuestions->pluck('pivot.cluster_id', 'id')->filter()->all();

        foreach ($userAnswers as $answer) {
            // Skip SDB questions - they are excluded from cluster calculations
            // Only P and R category questions are included
            if (!$answer['include_in_construct'] || strtoupper($answer['category'] ?? '') === 'SDB') {
                continue;
            }

            $clusterId = $questionIdToClusterId[$answer['question_id']] ?? null;

            // Fallback: get cluster from question->construct->cluster (legacy when pivot cluster_id was not set)
            if (!$clusterId) {
                $question = QuestionsModel::with('construct.cluster')->find($answer['question_id']);
                if (!$question || !$question->construct || !$question->construct->cluster) {
                    continue;
                }
                $clusterId = $question->construct->cluster->id;
            }

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
            $clusterArea = $cluster ? $cluster->area : null;
            
            $clusterScores[$clusterName] = [
                'total' => round($total, 2),
                'average' => $average,
                'percentage' => $percentage,
                'count' => $count,
                'category' => $this->categorizeScore($average),
                'area' => $clusterArea
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
            // Skip SDB questions - they are excluded from construct calculations
            // Only P and R category questions are included
            if (!$answer['include_in_construct'] || strtoupper($answer['category'] ?? '') === 'SDB') {
                continue;
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
                    'source' => $testResult->test->source ?? null,
                    'sc_pro_test_id' => $testResult->test->sc_pro_test_id ?? null,
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
     * For CERC tests, combines SC Pro answers with CERC answers
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

        // For CERC tests, combine SC Pro answers with CERC answers
        $allAnswers = $this->getCombinedAnswersForResult($testResult);

        // Format questions with answers
        $questionsWithAnswers = $allAnswers->map(function ($answerData) use ($options, $questionOrder) {
            $question = $answerData['question'];
            $answer = $answerData['answer'];
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
                    'from_sc_pro' => $answerData['from_sc_pro'] ?? false,
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
                'exam_taken_at' => $testResult->created_at,
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
     * Get combined answers for a test result (SC Pro + CERC for CERC tests)
     */
    private function getCombinedAnswersForResult($testResult)
    {
        $test = $testResult->test;
        $userId = $testResult->user_id;

        // For non-CERC tests, just return the test result's answers
        if ($test->source !== 'CERC') {
            return $testResult->answers->map(function ($answer) {
                return [
                    'question' => $answer->question,
                    'answer' => $answer,
                    'from_sc_pro' => false,
                ];
            });
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
                // CERC uses same question IDs as SC Pro. Map by question_id only.
                $scProAnswers = UserAnswer::where('test_result_id', $scProTestResult->id)->get();
                $scProAnswersById = $scProAnswers->keyBy('question_id');

                // Match SC Pro answers to CERC questions by question_id
                foreach ($cercTestQuestionIds as $cercQuestionId) {
                    $cercQuestion = $cercTestQuestions->get($cercQuestionId);
                    if (!$cercQuestion) {
                        continue;
                    }

                    $matchedAnswer = null;
                    $fromScPro = false;

                    if ($cercAnswers->has($cercQuestionId)) {
                        $matchedAnswer = $cercAnswers->get($cercQuestionId);
                        $fromScPro = false;
                    } elseif (isset($scProAnswersById[$cercQuestionId])) {
                        $matchedAnswer = $scProAnswersById[$cercQuestionId];
                        $fromScPro = true;
                    }

                    // If we found a match, add it to combined answers
                    if ($matchedAnswer) {
                        // For SC Pro answers, use the CERC question (so question_id matches CERC test structure)
                        // but use the SC Pro answer data
                        if ($fromScPro) {
                            $combinedAnswers->push([
                                'question' => $cercQuestion,
                                'answer' => $matchedAnswer,
                                'from_sc_pro' => true,
                            ]);
                        } else {
                            // For CERC answers, ensure question is loaded with relationships
                            if (!$matchedAnswer->relationLoaded('question') || !$matchedAnswer->question) {
                                $matchedAnswer->load('question.construct.cluster');
                            }
                            $combinedAnswers->push([
                                'question' => $matchedAnswer->question,
                                'answer' => $matchedAnswer,
                                'from_sc_pro' => false,
                            ]);
                        }
                    }
                }
            }
        }

        // If no SC Pro test or answers, just return CERC answers
        if ($combinedAnswers->isEmpty() && !$cercAnswers->isEmpty()) {
            return $cercAnswers->map(function ($answer) {
                $answer->load('question.construct.cluster');
                return [
                    'question' => $answer->question,
                    'answer' => $answer,
                    'from_sc_pro' => false,
                ];
            });
        }

        return $combinedAnswers;
    }

    /**
     * Get all test results for a user (lightweight - scores only)
     * Now includes source information and groups SC Pro and CERC results
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
                    'source' => $testResult->test->source ?? 'SC Pro',
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
            'message' => 'User test results fetched successfully'
        ], 200);
    }

    /**
     * Check if user can take CERC test (has completed SC Pro)
     */
    public function checkCercEligibility(Request $request, $testId)
    {
        $test = Test::find($testId);
        
        if (!$test) {
            return response()->json([
                'status' => false,
                'message' => 'Test not found'
            ], 404);
        }
        
        if ($test->source !== 'CERC') {
            return response()->json([
                'status' => false,
                'message' => 'This endpoint is only for CERC tests'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        $userId = $request->input('user_id');
        
        // Find SC Pro test - use explicit mapping if available, otherwise fallback to age group
        if ($test->sc_pro_test_id) {
            // Use explicit mapping
            $scProTest = Test::where('id', $test->sc_pro_test_id)
                ->where('source', 'SC Pro')
                ->where('is_active', true)
                ->first();
        } else {
            // Fallback: Find SC Pro test for same age group (backward compatibility)
            $scProTest = Test::where('source', 'SC Pro')
                ->where('is_active', true)
                ->where('age_group_id', $test->age_group_id)
                ->first();
        }
        
        if (!$scProTest) {
            return response()->json([
                'status' => false,
                'can_take_cerc' => false,
                'message' => 'SC Pro test not found for this age group',
                'sc_pro_test' => null
            ], 200);
        }
        
        // Check if user has completed SC Pro test
        $scProTestResult = TestResult::where('user_id', $userId)
            ->where('test_id', $scProTest->id)
            ->where('status', 'completed')
            ->latest()
            ->first();
        
        $canTakeCerc = $scProTestResult !== null;
        
        return response()->json([
            'status' => true,
            'can_take_cerc' => $canTakeCerc,
            'message' => $canTakeCerc 
                ? 'User is eligible to take CERC test' 
                : 'User must complete SC Pro test first',
            'sc_pro_test' => [
                'id' => $scProTest->id,
                'title' => $scProTest->title,
                'description' => $scProTest->description,
            ],
            'sc_pro_test_result' => $scProTestResult ? [
                'id' => $scProTestResult->id,
                'completed_at' => $scProTestResult->created_at,
            ] : null
        ], 200);
    }

    /**
     * Get available tests for a user (SC Pro and CERC if eligible)
     */
    public function getAvailableTests(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'age_group_id' => 'nullable|exists:age_groups,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        $userId = $request->input('user_id');
        $ageGroupId = $request->input('age_group_id');
        
        // Get all active tests
        $query = Test::where('is_active', true);
        
        if ($ageGroupId) {
            $query->where('age_group_id', $ageGroupId);
        }
        
        $tests = $query->get();
        
        // Get user's completed test results
        $userTestResults = TestResult::where('user_id', $userId)
            ->where('status', 'completed')
            ->pluck('test_id')
            ->toArray();
        
        $availableTests = $tests->map(function ($test) use ($userId, $userTestResults) {
            $isCompleted = in_array($test->id, $userTestResults);
            $canTake = true;
            $requiresScPro = false;
            $scProTestInfo = null;
            
            // For CERC tests, check if user has completed SC Pro
            if ($test->source === 'CERC') {
                // Find SC Pro test - use explicit mapping if available, otherwise fallback to age group
                if ($test->sc_pro_test_id) {
                    // Use explicit mapping
                    $scProTest = Test::where('id', $test->sc_pro_test_id)
                        ->where('source', 'SC Pro')
                        ->where('is_active', true)
                        ->first();
                } else {
                    // Fallback: Find SC Pro test for same age group (backward compatibility)
                    $scProTest = Test::where('source', 'SC Pro')
                        ->where('is_active', true)
                        ->where('age_group_id', $test->age_group_id)
                        ->first();
                }
                
                if ($scProTest) {
                    $scProCompleted = in_array($scProTest->id, $userTestResults);
                    $canTake = $scProCompleted;
                    $requiresScPro = true;
                    
                    $scProTestResult = TestResult::where('user_id', $userId)
                        ->where('test_id', $scProTest->id)
                        ->where('status', 'completed')
                        ->latest()
                        ->first();
                    
                    $scProTestInfo = [
                        'id' => $scProTest->id,
                        'title' => $scProTest->title,
                        'is_completed' => $scProCompleted,
                        'test_result_id' => $scProTestResult ? $scProTestResult->id : null,
                    ];
                }
            }
            
            return [
                'id' => $test->id,
                'title' => $test->title,
                'description' => $test->description,
                'source' => $test->source ?? 'SC Pro',
                'age_group_id' => $test->age_group_id,
                'is_completed' => $isCompleted,
                'can_take' => $canTake,
                'requires_sc_pro' => $requiresScPro,
                'sc_pro_test' => $scProTestInfo,
            ];
        });
        
        return response()->json([
            'status' => true,
            'data' => $availableTests,
            'message' => 'Available tests fetched successfully'
        ], 200);
    }

    /**
     * Get user test results grouped by source (SC Pro and CERC mapped together)
     */
    public function getUserResultsBySource($userId)
    {
        $testResults = TestResult::with(['test'])
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Group by source
        $scProResults = $testResults->filter(function ($result) {
            return ($result->test->source ?? 'SC Pro') === 'SC Pro';
        });
        
        $cercResults = $testResults->filter(function ($result) {
            return ($result->test->source ?? 'SC Pro') === 'CERC';
        });
        
        $formatResult = function ($testResult) {
            $radarChartData = $this->formatRadarChartData($testResult->cluster_scores);
            
            return [
                'test_result_id' => $testResult->id,
                'test' => [
                    'id' => $testResult->test->id,
                    'title' => $testResult->test->title,
                    'source' => $testResult->test->source ?? 'SC Pro',
                ],
                'scores' => [
                    'total_score' => $testResult->total_score,
                    'average_score' => $testResult->average_score,
                    'average_percentage' => round($this->convertToPercentage($testResult->average_score ?? 0), 0),
                    'cluster_scores' => $testResult->cluster_scores,
                    'construct_scores' => $testResult->construct_scores,
                ],
                'radar_chart' => $radarChartData,
                'submitted_at' => $testResult->created_at,
            ];
        };
        
        // Get the latest SC Pro and CERC results
        $latestScPro = $scProResults->first();
        $latestCerc = $cercResults->first();
        
        $response = [
            'status' => true,
            'data' => [
                'sc_pro' => [
                    'has_completed' => $scProResults->count() > 0,
                    'latest_result' => $latestScPro ? $formatResult($latestScPro) : null,
                    'all_results' => $scProResults->map($formatResult)->values(),
                    'total_completed' => $scProResults->count(),
                ],
                'cerc' => [
                    'has_completed' => $cercResults->count() > 0,
                    'can_take' => $scProResults->count() > 0, // Can take if SC Pro is completed
                    'latest_result' => $latestCerc ? $formatResult($latestCerc) : null,
                    'all_results' => $cercResults->map($formatResult)->values(),
                    'total_completed' => $cercResults->count(),
                ],
            ],
            'message' => 'User test results by source fetched successfully'
        ];
        
        return response()->json($response, 200);
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
            'age_group_id' => 'nullable|exists:age_groups,id',
            'test_id' => 'nullable|exists:tests,id',
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
        $ageGroupId = $request->input('age_group_id');
        $testId = $request->input('test_id');

        $testResults = $this->fetchTestResultsWithRelations($fromDate, $toDate, $ageGroupId, $testId);
        $formattedResults = $this->transformTestResults($testResults);

        return response()->json([
            'status' => true,
            'data' => $formattedResults,
            'total_results' => $formattedResults->count(),
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'age_group_id' => $ageGroupId,
                'test_id' => $testId,
            ],
            'message' => 'All test results fetched successfully'
        ], 200);
    }

    /**
     * Download comprehensive test results as an Excel file with multiple sheets.
     * Filtered by selected age group from session.
     * Optional: Pass user_ids (comma-separated or array) to export only selected users.
     */
    public function downloadTestResultsExcel(Request $request)
    {
        try {
            // Normalize user_ids: accept comma-separated string or array
            $userIdsInput = $request->input('user_ids');
            if (is_string($userIdsInput)) {
                $userIdsInput = array_filter(array_map('intval', explode(',', $userIdsInput)));
            } elseif (is_array($userIdsInput)) {
                $userIdsInput = array_filter(array_map('intval', $userIdsInput));
            } else {
                $userIdsInput = [];
            }

            $validator = Validator::make(array_merge($request->all(), ['user_ids' => $userIdsInput]), [
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date',
                'age_group_id' => 'required|exists:age_groups,id',
                'test_id' => 'nullable|exists:tests,id',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'integer|exists:users,id',
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
            $ageGroupId = $request->input('age_group_id');
            $testId = $request->input('test_id');

            $testResults = $this->fetchTestResultsWithRelations($fromDate, $toDate, $ageGroupId, $testId);
            $formattedResults = $this->transformTestResults($testResults);

            $filteredResults = $formattedResults->filter(function ($result) {
                return strtolower($result['user']['role'] ?? '') === 'user';
            })->values();

            // Filter by selected user IDs when provided
            if (!empty($userIdsInput)) {
                $filteredResults = $filteredResults->filter(function ($result) use ($userIdsInput) {
                    $userId = $result['user']['id'] ?? null;
                    return $userId && in_array((int) $userId, $userIdsInput, true);
                })->values();
            }

            // Check if there's any data to export
            if ($filteredResults->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No test results found to export',
                ], 404);
            }

            $fileName = 'user-test-results-' . now()->format('Y-m-d_His') . '.xlsx';

            // Group results by test_id
            $groupedByTest = $filteredResults->groupBy(function ($result) {
                return $result['test']['id'] ?? 'unknown';
            });

            // Build datasets for each test
            $testDatasets = [];
            foreach ($groupedByTest as $testId => $testResults) {
                $testTitle = $testResults->first()['test']['title'] ?? "Test {$testId}";
                $testDatasets[] = [
                    'test_id' => $testId,
                    'test_title' => $testTitle,
                    'raw_data' => $this->buildExportDatasets($testResults)['raw'],
                ];
            }

            // Ensure we have data to export
            if (empty($testDatasets) || empty(array_filter(array_column($testDatasets, 'raw_data')))) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data available to export',
                ], 404);
            }

            return Excel::download(
                new UserTestDataExport($testDatasets),
                $fileName
            );
        } catch (\Exception $e) {
            \Log::error('Excel export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate Excel file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download test results as Excel (new format: user details, clusters, constructs, SDB - no questions).
     *
     * One sheet per test; each sheet has Column A = labels, Columns B+ = one column per candidate (selected users
     * who took that test). When selecting specific users, they may appear in multiple tests → multiple sheets.
     *
     * Payload (GET query params):
     * - age_group_id: required when user_ids not provided; optional when user_ids is provided
     * - from_date, to_date: optional date filter
     * - test_id: optional — limit to one test
     * - user_ids: optional — array or comma-separated user ids; only these users' results are included
     *
     * GET test-results-comprehensive/export-summary
     */
    public function downloadTestResultsSummaryExcel(Request $request)
    {
        try {
            $userIdsInput = $request->input('user_ids');
            if (is_string($userIdsInput)) {
                $userIdsInput = array_filter(array_map('intval', explode(',', $userIdsInput)));
            } elseif (is_array($userIdsInput)) {
                $userIdsInput = array_filter(array_map('intval', $userIdsInput));
            } else {
                $userIdsInput = [];
            }

            $rules = [
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date',
                'age_group_id' => 'nullable|exists:age_groups,id',
                'test_id' => 'nullable|exists:tests,id',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'integer|exists:users,id',
            ];
            if (empty($userIdsInput)) {
                $rules['age_group_id'] = 'required|exists:age_groups,id';
            }

            $validator = Validator::make(array_merge($request->all(), ['user_ids' => $userIdsInput]), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $ageGroupId = $request->input('age_group_id');
            $testId = $request->input('test_id');

            $testResults = $this->fetchTestResultsWithRelations($fromDate, $toDate, $ageGroupId, $testId);
            $formattedResults = $this->transformTestResults($testResults);

            $filteredResults = $formattedResults->filter(function ($result) {
                return strtolower($result['user']['role'] ?? '') === 'user';
            })->values();

            if (!empty($userIdsInput)) {
                $filteredResults = $filteredResults->filter(function ($result) use ($userIdsInput) {
                    $userId = $result['user']['id'] ?? null;
                    return $userId && in_array((int) $userId, $userIdsInput, true);
                })->values();
            }

            if ($filteredResults->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No test results found to export',
                ], 404);
            }

            $fileName = 'test-results-summary-' . now()->format('Y-m-d_His') . '.xlsx';

            $groupedByTest = $filteredResults->groupBy(function ($result) {
                return $result['test']['id'] ?? 'unknown';
            });

            $testDatasets = [];
            foreach ($groupedByTest as $testIdVal => $testResultsGroup) {
                // Sort by user id so the same user is in the same column across sheets (when they took multiple tests)
                $sorted = $testResultsGroup->sortBy(fn ($r) => $r['user']['id'] ?? 0)->values();
                $testTitle = $sorted->first()['test']['title'] ?? "Test {$testIdVal}";
                $exportData = $this->buildSummaryExportDatasets($sorted);
                $testDatasets[] = [
                    'test_id' => $testIdVal,
                    'test_title' => $testTitle,
                    'raw_data' => $exportData['raw'] ?? [],
                ];
            }

            if (empty($testDatasets) || empty(array_filter(array_column($testDatasets, 'raw_data')))) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data available to export',
                ], 404);
            }

            return Excel::download(
                new UserTestDataExport($testDatasets),
                $fileName
            );
        } catch (\Exception $e) {
            \Log::error('Test results summary Excel export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate Excel file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch test results with required relationships and optional date filters.
     */
    protected function fetchTestResultsWithRelations(?string $fromDate, ?string $toDate, ?int $ageGroupId = null, ?int $testId = null): Collection
    {
        $query = TestResult::with([
            'user.ageGroup',
            'user.organization',
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

        // Filter by test ID if provided
        if ($testId) {
            $query->where('test_id', $testId);
        }

        // Filter by age group if provided (from session)
        if ($ageGroupId) {
            $query->whereHas('test', function ($q) use ($ageGroupId) {
                $q->where('age_group_id', $ageGroupId);
            });
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

            $test = $testResult->test;
            $questionOrder = $test->selectedQuestions
                ? $test->selectedQuestions->pluck('pivot.order_no', 'id')->toArray()
                : [];

            // Test-specific cluster per question (from test_question pivot) for correct Excel/display
            $questionIdToCluster = [];
            if ($test->selectedQuestions) {
                $pivotClusterIds = $test->selectedQuestions->pluck('pivot.cluster_id')->filter()->unique()->values()->all();
                $clustersById = !empty($pivotClusterIds)
                    ? Cluster::whereIn('id', $pivotClusterIds)->get()->keyBy('id')
                    : collect();
                foreach ($test->selectedQuestions as $sq) {
                    $cid = $sq->pivot->cluster_id ?? null;
                    if ($cid && $clustersById->has($cid)) {
                        $questionIdToCluster[$sq->id] = [
                            'id' => $cid,
                            'name' => $clustersById->get($cid)->name,
                        ];
                    }
                }
            }

            // For CERC tests, get combined answers (SC Pro + CERC)
            $answersToProcess = $testResult->answers;
            if ($testResult->test->source === 'CERC') {
                $combinedAnswers = $this->getCombinedAnswersForResult($testResult);
                // Convert the combined answers structure to a format compatible with the mapping below
                $answersToProcess = $combinedAnswers->map(function ($item) {
                    // The item has 'question' and 'answer' keys
                    // We need to ensure the answer has the question relationship
                    $answer = $item['answer'];
                    $question = $item['question'];
                    // Always set the question relationship on the answer (overwrite if needed)
                    // This ensures SC Pro answers use CERC question structure
                    if (is_object($answer) && is_object($question)) {
                        $answer->setRelation('question', $question);
                    }
                    return $answer;
                });
            }

            $questionsWithAnswers = $answersToProcess->map(function ($answer) use ($options, $questionOrder, $questionIdToCluster) {
                $question = $answer->question;
                if (!$question) {
                    return null;
                }

                $optionLabel = $options->get($answer->answer_value);

                // Use test-specific cluster (from test_question) when available; else fallback to construct->cluster
                $clusterForQuestion = $questionIdToCluster[$question->id] ?? null;
                if (!$clusterForQuestion && $question->construct && $question->construct->cluster) {
                    $clusterForQuestion = [
                        'id' => $question->construct->cluster->id,
                        'name' => $question->construct->cluster->name,
                    ];
                }

                return [
                    'question_id' => $question->id,
                    'question_text' => $question->question_text,
                    'category' => $question->category,
                    'order_no' => $questionOrder[$question->id] ?? null,
                    'construct' => $question->construct ? [
                        'id' => $question->construct->id,
                        'name' => $question->construct->name,
                        'cluster' => $clusterForQuestion ? [
                            'id' => $clusterForQuestion['id'],
                            'name' => $clusterForQuestion['name'],
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

            // Calculate SDB scores
            $sdbScores = $this->calculateSDBScores($questionsWithAnswers->toArray());

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
                    'age_group' => $testResult->user->ageGroup?->name,
                    'city' => $testResult->user->city,
                    'state' => $testResult->user->state,
                    'country' => $testResult->user->country,
                    'profession' => $testResult->user->profession,
                    'educational_qualification' => $testResult->user->educational_qualification,
                    'organization' => $testResult->user->organization?->name,
                    'department' => null,
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
                    'raw' => $sdbScores['raw'],
                    'percentage' => $sdbScores['percentage'] !== null ? round($sdbScores['percentage'], 0) : null,
                    'band' => $sdbScores['band'],
                    'band_name' => $sdbScores['band_name'],
                ],
                'sdb_flag' => $testResult->sdb_flag,
                'status' => $testResult->status,
                'submitted_at' => optional($testResult->created_at)->toDateTimeString(),
                'updated_at' => optional($testResult->updated_at)->toDateTimeString(),
            ];
        })->filter()->values();
    }

    /**
     * Build datasets for Excel export (previous format: Cluster, Construct, Question Text + users as columns).
     */
    protected function buildExportDatasets(Collection $results): array
    {
        $userHeaders = $this->getUserHeaderLabels();
        $questionCols = $this->buildQuestionColumnsMeta($results);
        $clusterNames = $this->collectSummaryNames($results, 'clusters');
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
     * Build datasets for summary Excel export (new format: user details, clusters, constructs, SDB - no questions).
     */
    protected function buildSummaryExportDatasets(Collection $results): array
    {
        $clusterNames = $this->collectSummaryNames($results, 'clusters');
        $constructNames = $this->collectSummaryNames($results, 'constructs');

        $rawRows = $this->buildSummaryExportRows($results, $clusterNames, $constructNames);

        return [
            'raw' => $rawRows,
        ];
    }

    /**
     * Build summary export rows: Column A = labels, Columns B+ = one candidate per column.
     * Content: User details, clusters, constructs, SDB. No questions.
     *
     * @param  array<int, string>  $clusterNames
     * @param  array<int, string>  $constructNames
     */
    protected function buildSummaryExportRows(
        Collection $results,
        array $clusterNames,
        array $constructNames
    ): array {
        $userDetailLabels = [
            'Candidate Name',
            'assessment_date',
            'age',
            'age_group',
            'department',
            'Organization',
            'City',
            'State',
            'Country',
        ];

        $rows = [];

        // 1. User details (row-wise: label in A, each user's value in B, C, D...)
        foreach ($userDetailLabels as $label) {
            $row = [$label];
            foreach ($results as $result) {
                $user = $result['user'] ?? [];
                $submittedAt = $result['submitted_at'] ?? null;
                $assessmentDate = $submittedAt ? (is_string($submittedAt) ? substr($submittedAt, 0, 10) : Carbon::parse($submittedAt)->format('Y-m-d')) : null;

                $value = match ($label) {
                    'Candidate Name' => $user['name'] ?? '',
                    'assessment_date' => $assessmentDate ?? '',
                    'age' => $user['age'] ?? '',
                    'age_group' => $user['age_group'] ?? '',
                    'department' => $user['department'] ?? '',
                    'Organization' => $user['organization'] ?? '',
                    'City' => $user['city'] ?? '',
                    'State' => $user['state'] ?? '',
                    'Country' => $user['country'] ?? '',
                    default => '',
                };
                $row[] = $value;
            }
            $rows[] = $row;
        }

        // 2. Clusters (name in A, each user's score in B, C, D...)
        foreach ($clusterNames as $clusterName) {
            $row = [$clusterName];
            foreach ($results as $result) {
                $clusterItems = $result['clusters'] ?? [];
                $entry = $clusterItems[$clusterName] ?? null;
                $score = $entry ? $this->extractPercentageScore($entry) : null;
                $row[] = $score;
            }
            $rows[] = $row;
        }

        // 3. Constructs (name in A, each user's score in B, C, D...)
        foreach ($constructNames as $constructName) {
            $row = [$constructName];
            foreach ($results as $result) {
                $constructItems = $result['constructs'] ?? [];
                $entry = $constructItems[$constructName] ?? null;
                $score = $entry ? $this->extractPercentageScore($entry) : null;
                $row[] = $score;
            }
            $rows[] = $row;
        }

        // 4. SDB
        $sdbRow = ['SDB'];
        foreach ($results as $result) {
            $sdb = $result['sdb'] ?? [];
            $sdbRow[] = $sdb['percentage'] ?? null;
        }
        $rows[] = $sdbRow;

        return $rows;
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
                    'category' => $this->categorizeByPercentage($percentage),
                    'area' => $data['area'] ?? null,
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
                    'area' => null,
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
     * Build the raw data sheet rows (transposed format: questions as rows, users as columns).
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
        $rows = [];
        
        // Build user identifier columns with "Name, Gender, Age" format
        $userColumnHeaders = [];
        foreach ($results as $result) {
            $user = $result['user'] ?? [];
            $name = $user['name'] ?? 'User ' . ($user['id'] ?? '');
            $gender = $user['gender'] ?? '';
            $age = $user['age'] ?? '';
            $userColumnHeaders[] = trim("{$name}, {$gender}, {$age}", ', ');
        }

        // First row: Question metadata headers + User names (without Question column)
        $firstRow = ['Cluster', 'Construct', 'Question Text'];
        $firstRow = array_merge($firstRow, $userColumnHeaders);
        $rows[] = $firstRow;

        // Build a map of question_id => user_id => answer value
        $questionUserMap = [];
        foreach ($results as $result) {
            $userId = $result['user']['id'] ?? null;
            if (!$userId) continue;

            $questionMap = $this->indexQuestionsById($result['questions'] ?? []);
            foreach ($questionColumns as $column) {
                $questionId = $column['question_id'];
                $question = $questionMap[$questionId] ?? null;
                $answerValue = data_get($question, 'answer.final_score');
                
                // Get category from column metadata (already stored in buildQuestionColumnsMeta)
                $category = $column['category'] ?? '';
                
                if (!isset($questionUserMap[$questionId])) {
                    $questionUserMap[$questionId] = [
                        'cluster' => $column['cluster'],
                        'construct' => $column['construct'],
                        'question_text' => $column['question_text'],
                        'category' => $category,
                        'users' => [],
                    ];
                }
                $questionUserMap[$questionId]['users'][$userId] = $answerValue;
            }
        }

        // Build rows: one row per question with values for all users
        // Track previous cluster/construct to only show when they change
        $previousCluster = null;
        $previousConstruct = null;
        
        foreach ($questionColumns as $column) {
            $questionId = $column['question_id'];
            $questionData = $questionUserMap[$questionId] ?? null;
            
            if (!$questionData) continue;

            // Combine question text with category (P, R, SDB)
            $questionText = $questionData['question_text'] ?? '';
            $category = $questionData['category'] ?? '';
            $questionTextWithCategory = $questionText;
            if ($category) {
                $questionTextWithCategory = "{$questionText} ({$category})";
            }

            // Only show cluster/construct when they change
            $currentCluster = $column['cluster'];
            $currentConstruct = $column['construct'];
            
            $clusterValue = ($currentCluster !== $previousCluster) ? $currentCluster : '';
            $constructValue = ($currentConstruct !== $previousConstruct) ? $currentConstruct : '';

            $row = [
                $clusterValue,
                $constructValue,
                $questionTextWithCategory,
            ];

            // Add answer values for each user in order
            foreach ($results as $result) {
                $userId = $result['user']['id'] ?? null;
                $row[] = $questionData['users'][$userId] ?? null;
            }

            $rows[] = $row;
            
            // Update previous values
            $previousCluster = $currentCluster;
            $previousConstruct = $currentConstruct;
        }

        // Add summary rows: Clusters, Constructs, SDB
        $rows[] = []; // Empty row separator

        // Cluster summary row (3 columns: Cluster, Construct, Question Text)
        $clusterRow = ['Clusters', '', ''];
        foreach ($results as $result) {
            $clusterItems = data_get($result, 'clusters', []);
            $clusterValue = null;
            if (!empty($clusterNames) && isset($clusterNames[0])) {
                $entry = $clusterItems[$clusterNames[0]] ?? null;
                $clusterValue = $entry ? $this->extractPercentageScore($entry) : null;
            }
            $clusterRow[] = $clusterValue;
        }
        $rows[] = $clusterRow;

        // Individual cluster rows
        foreach ($clusterNames as $clusterName) {
            $clusterRow = ['', $clusterName, ''];
            foreach ($results as $result) {
                $clusterItems = data_get($result, 'clusters', []);
                $entry = $clusterItems[$clusterName] ?? null;
                $clusterRow[] = $entry ? $this->extractPercentageScore($entry) : null;
            }
            $rows[] = $clusterRow;
        }

        $rows[] = []; // Empty row separator

        // Construct summary row
        $constructRow = ['Constructs', '', ''];
        foreach ($results as $result) {
            $constructItems = data_get($result, 'constructs', []);
            $constructValue = null;
            if (!empty($constructNames) && isset($constructNames[0])) {
                $entry = $constructItems[$constructNames[0]] ?? null;
                $constructValue = $entry ? $this->extractPercentageScore($entry) : null;
            }
            $constructRow[] = $constructValue;
        }
        $rows[] = $constructRow;

        // Individual construct rows
        foreach ($constructNames as $constructName) {
            $constructRow = ['', $constructName, ''];
            foreach ($results as $result) {
                $constructItems = data_get($result, 'constructs', []);
                $entry = $constructItems[$constructName] ?? null;
                $constructRow[] = $entry ? $this->extractPercentageScore($entry) : null;
            }
            $rows[] = $constructRow;
        }

        $rows[] = []; // Empty row separator

        // SDB rows
        $sdbRawRow = ['SDB', 'Raw', ''];
        $sdbPercentRow = ['', 'Percentage', ''];
        $sdbBandRow = ['', 'Band', ''];

        foreach ($results as $result) {
            try {
                $questions = $result['questions'] ?? [];
                if ($questions instanceof Collection) {
                    $questions = $questions->toArray();
                }
                $sdbScores = $this->calculateSDBScores($questions);
                $sdbRawRow[] = $sdbScores['raw'];
                $sdbPercentRow[] = $sdbScores['percentage'] !== null ? round($sdbScores['percentage'], 0) : null;
                $sdbBandRow[] = $sdbScores['band_name'];
            } catch (\Exception $e) {
                \Log::error('SDB calculation error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $sdbRawRow[] = null;
                $sdbPercentRow[] = null;
                $sdbBandRow[] = null;
            }
        }

        $rows[] = $sdbRawRow;
        $rows[] = $sdbPercentRow;
        $rows[] = $sdbBandRow;

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

            $questionNumber = 1;

            foreach (array_keys($constructPositions) as $constructName) {
                $questions = $constructs[$constructName]['questions'] ?? [];
                uasort($questions, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

                foreach ($questions as $meta) {
                    $columns[] = [
                        'cluster' => $clusterName,
                        'construct' => $constructName,
                        'question_id' => $meta['question_id'],
                        'question_text' => $meta['question_text'],
                        'category' => $meta['category'] ?? '',
                        'label' => sprintf('Q%d(%s)', $questionNumber, $meta['category']),
                    ];
                    $questionNumber++;
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
     * Uses stored percentage when present so Excel matches result and report (exact value, 2 decimals).
     */
    protected function extractPercentageScore(array $entry): ?float
    {
        $percentage = $entry['percentage'] ?? null;

        if ($percentage === null && isset($entry['average'])) {
            $percentage = $this->calculatePercentageFromMean($entry['average']);
        }

        return $percentage !== null ? round((float) $percentage, 2) : null;
    }

    /**
     * Calculate percentage from mean score using formula: ((mean - 1) / 4) * 100
     * Example: 3.57 -> ((3.57 - 1) / 4) * 100 = 64.25%
     * Returns 2 decimal places to match stored values (result/report/Excel exact match).
     */
    private function calculatePercentageFromMean($meanScore)
    {
        if ($meanScore <= 0) {
            return 0.0;
        }
        $step2 = ($meanScore - 1) / 4;
        $percentage = round($step2 * 100, 2);
        return max(0, min(100, (float) $percentage));
    }

    /**
     * Categorize score by percentage: 0-59 = low, 60-75 = medium, 76-100 = high
     * Lowercase to match stored values (result/report/Excel exact match).
     */
    private function categorizeByPercentage($percentage)
    {
        if ($percentage < 60) {
            return 'low';
        } elseif ($percentage < 76) {
            return 'medium';
        } else {
            return 'high';
        }
    }

    /**
     * Calculate SDB (Social Desirability Bias) scores for a test result
     * Returns: ['raw' => float, 'percentage' => float, 'band' => string, 'band_name' => string]
     */
    protected function calculateSDBScores($questions): array
    {
        // Convert Collection to array if needed
        if ($questions instanceof Collection) {
            $questions = $questions->toArray();
        }
        
        // Ensure it's an array
        if (!is_array($questions)) {
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
            'percentage' => $sdbPercentage, // Already rounded in calculatePercentageFromMean
            'band' => is_array($band) ? ($band['band'] ?? null) : null,
            'band_name' => is_array($band) ? ($band['name'] ?? null) : null,
        ];
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
}
