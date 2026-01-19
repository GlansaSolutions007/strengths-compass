<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\QuestionsModel as Question;
use App\Models\Construct;
use App\Models\QuestionTranslation;
use App\Models\Language;
use App\Models\QuestionTranslationImport;
use App\Imports\QuestionsImport;
use App\Exports\QuestionTranslationTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionsController extends Controller
{
    /**
     * Display a listing of the resource.
     * Supports language translations via 'lang' parameter (default: 'en')
     * If translation exists, returns translated_text; otherwise returns question_text
     */
    public function index(Request $request)
    {
        $query = Question::with(['construct', 'ageGroup']);

        if ($request->has('construct_id')) {
            $query->where('construct_id', $request->construct_id);
        }

        // Filter by age_group_id if provided in request
        if ($request->has('age_group_id')) {
            $query->where('age_group_id', $request->age_group_id);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $questions = $query->orderBy('order_no')->get();

        // Load all translations for all questions
        $questionIds = $questions->pluck('id')->toArray();
        $allTranslations = QuestionTranslation::whereIn('question_id', $questionIds)
            ->where('is_active', true)
            ->with('language')
            ->get()
            ->groupBy('question_id');

        // Handle language filter (if lang parameter is provided, show only that language)
        $lang = $request->input('lang', null);
        $languageId = null;
        if ($lang && $lang !== 'en') {
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

        // Map questions with all translations
        $questions = $questions->map(function ($question) use ($allTranslations, $languageId) {
            $questionArray = $question->toArray();
            
            // Get translations for this question
            $translations = $allTranslations->get($question->id, collect());
            
            // Format translations with language info
            $translationsArray = $translations->map(function ($translation) {
                $text = $translation->translated_text;
                // Ensure UTF-8 encoding
                if (!mb_check_encoding($text, 'UTF-8')) {
                    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
                }
                return [
                    'id' => $translation->id,
                    'language_id' => $translation->language_id,
                    'language_name' => $translation->language->name ?? null,
                    'language_code' => $translation->language->code ?? null,
                    'translated_text' => $text,
                    'is_active' => $translation->is_active,
                    'created_at' => $translation->created_at,
                    'updated_at' => $translation->updated_at,
                ];
            })->values()->toArray();
            
            $questionArray['translations'] = $translationsArray;
            $questionArray['translation_count'] = count($translationsArray);
            
            // If specific language is requested, also set it as the primary question_text
            if ($languageId) {
                $specificTranslation = $translations->firstWhere('language_id', $languageId);
                if ($specificTranslation) {
                    $translatedText = $specificTranslation->translated_text;
                    if (!mb_check_encoding($translatedText, 'UTF-8')) {
                        $translatedText = mb_convert_encoding($translatedText, 'UTF-8', 'UTF-8');
                    }
                    $questionArray['question_text'] = $translatedText;
                    $questionArray['is_translated'] = true;
                } else {
                    $questionArray['is_translated'] = false;
                }
            } else {
                $questionArray['is_translated'] = count($translationsArray) > 0;
            }
            
            return $questionArray;
        });

        return response()->json([
            'status' => true,
            'data' => $questions,
            'message' => 'Questions fetched successfully',
            'language' => $lang,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'construct_id' => 'required|exists:constructs,id',
            'question_text' => 'required|string',
            'age_group_id' => 'nullable|exists:age_groups,id',
            'category' => 'required|in:P,R,SDB',
            'order_no' => 'required|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prepare data for creation - age_group_id will be included if provided
        $question = Question::create($request->only([
            'construct_id', 'question_text', 'age_group_id', 'category', 'order_no', 'is_active'
        ]));

        $question->load('construct');

        return response()->json([
            'status' => true,
            'message' => 'Question created successfully',
            'data' => $question,
        ], 201);
    }

    /**
     * Display the specified resource.
     * Supports language translations via 'lang' parameter (default: 'en')
     * If translation exists, returns translated_text; otherwise returns question_text
     */
    public function show(Request $request, string $id)
    {
        $question = Question::with(['construct', 'ageGroup'])->find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found',
            ], 404);
        }

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

        // If language is specified and found, load translation
        $questionArray = $question->toArray();
        
        if ($languageId) {
            $translation = QuestionTranslation::where('question_id', $question->id)
                ->where('language_id', $languageId)
                ->where('is_active', true)
                ->first();

            if ($translation) {
                $questionArray['question_text'] = $translation->translated_text;
                $questionArray['is_translated'] = true;
            } else {
                $questionArray['is_translated'] = false;
            }
        } else {
            $questionArray['is_translated'] = false;
        }

        return response()->json([
            'status' => true,
            'data' => $questionArray,
            'message' => 'Question fetched successfully',
            'language' => $lang,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $question = Question::find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'construct_id' => 'sometimes|required|exists:constructs,id',
            'question_text' => 'sometimes|required|string',
            'age_group_id' => 'nullable|exists:age_groups,id',
            'category' => 'sometimes|required|in:P,R,SDB',
            'order_no' => 'sometimes|required|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update with all provided fields including age_group_id
        $question->update($request->only([
            'construct_id', 'question_text', 'age_group_id', 'category', 'order_no', 'is_active'
        ]));

        $question->load('construct');

        return response()->json([
            'status' => true,
            'message' => 'Question updated successfully',
            'data' => $question,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $question = Question::find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found',
            ], 404);
        }

        $question->delete();

        return response()->json([
            'status' => true,
            'message' => 'Question deleted successfully',
        ], 200);
    }

    /**
     * List questions by construct ID
     * Supports language translations via 'lang' parameter (default: 'en')
     * If translation exists, returns translated_text; otherwise returns question_text
     */
    public function byConstruct(Request $request, string $constructId)
    {
        $construct = Construct::find($constructId);

        if (!$construct) {
            return response()->json([
                'status' => false,
                'message' => 'Construct not found',
            ], 404);
        }

        $questions = Question::where('construct_id', $constructId)
            ->orderBy('order_no')
            ->get();

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
        if ($languageId) {
            $questionIds = $questions->pluck('id')->toArray();
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
        } else {
            $translations = [];
        }

        // Map questions with translations
        $questions = $questions->map(function ($question) use ($translations) {
            $questionArray = $question->toArray();
            
            // Replace question_text with translated_text if translation exists
            if (isset($translations[$question->id])) {
                $translatedText = $translations[$question->id];
                // Ensure UTF-8 encoding
                if (!mb_check_encoding($translatedText, 'UTF-8')) {
                    $translatedText = mb_convert_encoding($translatedText, 'UTF-8', 'UTF-8');
                }
                $questionArray['question_text'] = $translatedText;
                $questionArray['is_translated'] = true;
            } else {
                $questionArray['is_translated'] = false;
            }
            
            return $questionArray;
        });

        return response()->json([
            'status' => true,
            'data' => $questions,
            'message' => 'Questions fetched successfully',
            'language' => $lang,
        ], 200);
    }

    /**
     * Bulk upload questions from Excel file
     * 
     * Questions are uploaded without construct assignment.
     * Use assignConstruct or bulkAssignConstruct to assign questions to constructs later.
     */
    public function bulkUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // 10MB max
            'construct_id' => 'nullable|exists:constructs,id',
            'age_group_id' => 'nullable|exists:age_groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        try {
            $file = $request->file('file');

            // Get construct_id and age_group_id from request
            $constructId = $request->input('construct_id');
            $ageGroupId = $request->input('age_group_id');

            // Create import instance with construct_id and age_group_id (if provided)
            // These will be used as fallback if not present in Excel file
            $import = new QuestionsImport($constructId, $ageGroupId);

            // Import the file
            Excel::import($import, $file);

            // Get import statistics
            $stats = $import->getStats();

            // Prepare response
            $response = [
                'status' => true,
                'message' => 'Bulk upload completed',
                'data' => [
                    'success_count' => $stats['success'],
                    'failure_count' => $stats['failures'],
                    'total_processed' => $stats['success'] + $stats['failures'],
                    'construct_id' => $constructId,
                    'age_group_id' => $ageGroupId,
                ],
            ];

            // Add failure details if any
            if ($stats['failures'] > 0) {
                // Get failures from the import instance (provided by SkipsFailures trait)
                $failures = method_exists($import, 'failures') ? $import->failures() : collect();
                $failureDetails = [];

                foreach ($failures as $failure) {
                    $failureDetails[] = [
                        'row' => $failure->row(),
                        'attribute' => $failure->attribute(),
                        'errors' => $failure->errors(),
                        'values' => $failure->values(),
                    ];
                }
                
                $response['data']['failures'] = $failureDetails;
                $response['data']['errors'] = $stats['errors'];
            }

            // Determine HTTP status code
            $statusCode = $stats['failures'] > 0 ? 207 : 200; // 207 Multi-Status if partial success

            return response()->json($response, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing Excel file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign a single question to a construct
     */
    public function assignConstruct(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'construct_id' => 'required|exists:constructs,id',
            'age_group_id' => 'nullable|exists:age_groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        $question = Question::find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found',
            ], 404);
        }

        $constructId = $request->input('construct_id');
        $construct = Construct::find($constructId);

        if (!$construct) {
            return response()->json([
                'status' => false,
                'message' => 'Construct not found',
            ], 404);
        }

        $question->construct_id = $constructId;
        
        // Update age_group_id if provided in request
        if ($request->has('age_group_id')) {
            $question->age_group_id = $request->input('age_group_id');
        }
        
        $question->save();
        $question->load('construct');

        return response()->json([
            'status' => true,
            'message' => 'Question assigned to construct successfully',
            'data' => $question,
        ], 200);
    }

    /**
     * Bulk assign multiple questions to a construct
     */
    public function bulkAssignConstruct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_ids' => 'required|array',
            'question_ids.*' => 'required|exists:questions,id',
            'construct_id' => 'required|exists:constructs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        $questionIds = $request->input('question_ids');
        $constructId = $request->input('construct_id');

        $construct = Construct::find($constructId);
        if (!$construct) {
            return response()->json([
                'status' => false,
                'message' => 'Construct not found',
            ], 404);
        }

        // Verify all questions exist
        $questions = Question::whereIn('id', $questionIds)->get();
        if ($questions->count() !== count($questionIds)) {
            return response()->json([
                'status' => false,
                'message' => 'Some questions were not found',
            ], 404);
        }

        // Bulk update
        $updated = Question::whereIn('id', $questionIds)
            ->update(['construct_id' => $constructId]);

        return response()->json([
            'status' => true,
            'message' => "Successfully assigned {$updated} question(s) to construct",
            'data' => [
                'updated_count' => $updated,
                'total_requested' => count($questionIds),
            ],
        ], 200);
    }

    /**
     * Toggle the is_active status of a question
     * Admin only when authentication is enabled
     */
    public function toggleActive(Request $request, string $id)
    {
        $currentUser = $request->user();
        $hasAuthToken = $request->bearerToken() || $request->hasHeader('Authorization');

        // Check admin access if authenticated
        if ($hasAuthToken && $currentUser && $currentUser->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden - Admin access required'
            ], 403);
        }

        $question = Question::find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found'
            ], 404);
        }

        // Toggle is_active
        $question->is_active = !$question->is_active;
        $question->save();
        $question->load('construct');

        return response()->json([
            'status' => true,
            'message' => 'Question active status toggled successfully',
            'data' => $question
        ], 200);
    }

    /**
     * Export all active questions as XLSX template for translations
     * Admin only
     * 
     * Headers: question_id, question_en, language, translated_text
     * - question_en comes from questions.question_text
     * - language and translated_text are empty (for translators to fill)
     * - Optional: Filter by age_group_id parameter
     * - Exports as XLSX format (Excel stores text as Unicode - safe for Telugu, Hindi, Tamil, etc.)
     */
    public function exportTranslationTemplate(Request $request)
    {
        $query = Question::where('is_active', true);

        if ($request->has('age_group_id')) {
            $query->where('age_group_id', $request->age_group_id);
        }

        $questions = $query->orderBy('id')->get(['id', 'question_text']);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="question_translation_template.csv"',
        ];

        $callback = function () use ($questions) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM (important for Excel)
            fwrite($handle, "\xEF\xBB\xBF");
            
            // Headings
            fputcsv($handle, [
                'question_id',
                'question_en',
                'language',
                'translated_text'
            ]);
            
            foreach ($questions as $question) {
                fputcsv($handle, [
                    $question->id,
                    $question->question_text,
                    '',
                    '',
                ]);
            }
            
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }


    /**
     * Import question translations from CSV or Excel file
     * Admin only
     * 
     * CSV columns: question_id, question_en, language, translated_text
     * - CSV files MUST be UTF-8 encoded (rejects non-UTF-8 files)
     * - Excel files (XLSX/XLS) are safe - they store text as Unicode internally
     * - Ignores question_en column
     * - Validates question_id exists
     * - Maps language column (full name like "Telugu", "Hindi" or code like "te", "hi") to language records
     * - Validates language exists and is active (rejects rows where language name does not exist)
     * - Skips rows with empty translated_text
     * - Inserts or updates translation based on (question_id + language)
     */
    public function importTranslations(Request $request)
    {
        $currentUser = $request->user();
        $hasAuthToken = $request->bearerToken() || $request->hasHeader('Authorization');

        // Check admin access if authenticated
        if ($hasAuthToken && $currentUser && $currentUser->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden - Admin access required'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $isExcel = in_array($fileExtension, ['xlsx', 'xls']);
        
        $stats = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $chunkSize = 100; // Process 100 rows at a time
        $importRecord = null;

        try {
            // Pre-load all active languages for faster lookup
            $languages = Language::where('is_active', true)->get();
            $languageMap = [];
            foreach ($languages as $lang) {
                $languageMap[strtolower($lang->name)] = $lang;
                $languageMap[strtolower($lang->code)] = $lang;
            }

            // Pre-load all questions for faster lookup
            $questions = Question::pluck('id')->toArray();
            $questionSet = array_flip($questions);

            $headers = [];
            $rows = [];
            $questionIdIndex = null;
            $languageIndex = null;
            $translatedTextIndex = null;

            // Process file based on type
            if ($isExcel) {
                // Read Excel file
                // Excel stores text internally as Unicode, so Telugu, Hindi, Tamil, etc. are safe
                // No encoding loss - Laravel Excel library reads Unicode correctly
                // Create a simple import class to read the Excel file
                $import = new class implements \Maatwebsite\Excel\Concerns\ToArray {
                    public function array(array $array)
                    {
                        // This method is required but won't be used
                    }
                };
                $data = Excel::toArray($import, $file);
                if (empty($data) || empty($data[0])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Excel file is empty or invalid',
                    ], 422);
                }

                $rows = $data[0];
                $headers = array_shift($rows); // Remove header row

                // Normalize headers (trim, lowercase for comparison)
                $normalizedHeaders = array_map(function($header) {
                    return strtolower(trim($header));
                }, $headers);

                // Find column indices
                $questionIdIndex = array_search('question_id', $normalizedHeaders);
                $languageIndex = array_search('language', $normalizedHeaders);
                $translatedTextIndex = array_search('translated_text', $normalizedHeaders);

                // Validate required columns exist
                if ($questionIdIndex === false || $languageIndex === false || $translatedTextIndex === false) {
                    return response()->json([
                        'status' => false,
                        'message' => 'File must contain columns: question_id, language, translated_text',
                        'found_columns' => $headers,
                    ], 422);
                }
            } else {
                // Process CSV file with UTF-8 encoding validation
                $filePath = $file->getRealPath();
                
                try {
                    // Read file content
                    $fileContent = file_get_contents($filePath);
                    
                    if ($fileContent === false) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Failed to read CSV file',
                        ], 422);
                    }
                    
                    // Remove UTF-8 BOM if present (BOM is valid, just remove it for processing)
                    $hasBom = substr($fileContent, 0, 3) === "\xEF\xBB\xBF";
                    if ($hasBom) {
                        $fileContent = substr($fileContent, 3);
                    }
                    
                    // STRICT UTF-8 VALIDATION: Reject files that are not valid UTF-8
                    if (!mb_check_encoding($fileContent, 'UTF-8')) {
                        // Detect what encoding it might be
                    $detectedEncoding = mb_detect_encoding($fileContent, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
                    
                        return response()->json([
                            'status' => false,
                            'message' => 'CSV file must be UTF-8 encoded. Detected encoding: ' . ($detectedEncoding ?: 'unknown') . '. Please convert your file to UTF-8 before uploading.',
                            'detected_encoding' => $detectedEncoding,
                            'hint' => 'Use Excel or a text editor to save the file as UTF-8 CSV format.',
                        ], 422);
                    }
                    
                    // Additional validation: Check for invalid UTF-8 sequences
                    // This catches cases where mb_check_encoding might pass but file has issues
                    if (!@mb_convert_encoding($fileContent, 'UTF-8', 'UTF-8')) {
                        return response()->json([
                            'status' => false,
                            'message' => 'CSV file contains invalid UTF-8 sequences. Please ensure the file is properly UTF-8 encoded.',
                        ], 422);
                    }
                    
                    // Create temporary file with UTF-8 content
                    $tempFile = tempnam(sys_get_temp_dir(), 'csv_import_');
                    if ($tempFile === false) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Failed to create temporary file',
                        ], 422);
                    }
                    
                    file_put_contents($tempFile, $fileContent);
                    
                    // Open file
                    $handle = fopen($tempFile, 'r');
                    
                    if (!$handle) {
                        @unlink($tempFile);
                        return response()->json([
                            'status' => false,
                            'message' => 'Failed to open CSV file',
                        ], 422);
                    }
                    
                    // Read and skip header row
                    $headers = fgetcsv($handle);
                    if (!$headers || empty($headers)) {
                        fclose($handle);
                        @unlink($tempFile);
                        return response()->json([
                            'status' => false,
                            'message' => 'CSV file is empty or invalid',
                        ], 422);
                    }

                    // Clean and normalize headers (file is already validated as UTF-8)
                    $headers = array_map(function($header) {
                        return trim($header);
                    }, $headers);

                    // Normalize headers (trim, lowercase for comparison)
                    $normalizedHeaders = array_map(function($header) {
                        return mb_strtolower(trim($header), 'UTF-8');
                    }, $headers);

                    // Find column indices
                    $questionIdIndex = array_search('question_id', $normalizedHeaders);
                    $languageIndex = array_search('language', $normalizedHeaders);
                    $translatedTextIndex = array_search('translated_text', $normalizedHeaders);

                    // Validate required columns exist
                    if ($questionIdIndex === false || $languageIndex === false || $translatedTextIndex === false) {
                        fclose($handle);
                        @unlink($tempFile);
                        return response()->json([
                            'status' => false,
                            'message' => 'CSV file must contain columns: question_id, language, translated_text',
                            'found_columns' => $headers,
                        ], 422);
                    }

                    // Read all CSV rows (file is already validated as UTF-8)
                    while (($row = fgetcsv($handle)) !== false) {
                        // Validate each cell is still UTF-8 (should be, but double-check)
                        $row = array_map(function($cell) {
                            if ($cell !== null && $cell !== '' && !mb_check_encoding($cell, 'UTF-8')) {
                                // This should not happen since file was validated, but log if it does
                                Log::warning('CSV cell encoding issue detected after file validation', [
                                    'cell_preview' => mb_substr($cell, 0, 50),
                                ]);
                                // Reject the file if we find invalid UTF-8 in cells
                                throw new \Exception('Invalid UTF-8 encoding detected in CSV data. File validation failed.');
                            }
                            return $cell;
                        }, $row);
                        $rows[] = $row;
                    }
                    fclose($handle);
                    @unlink($tempFile);
                    
                } catch (\Exception $e) {
                    if (isset($handle) && is_resource($handle)) {
                        fclose($handle);
                    }
                    if (isset($tempFile) && file_exists($tempFile)) {
                        @unlink($tempFile);
                    }
                    throw $e;
                }
            }

            $rowNumber = 1; // Start from 1 (header is row 0)
            $chunk = [];
            $totalRows = 0;

            // Process rows (from CSV or Excel)
            foreach ($rows as $row) {
                $rowNumber++;
                $totalRows++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Extract values (handle both array and CSV row formats)
                $questionId = isset($row[$questionIdIndex]) ? trim((string)$row[$questionIdIndex]) : null;
                $languageInput = isset($row[$languageIndex]) ? trim((string)$row[$languageIndex]) : null;
                $translatedText = isset($row[$translatedTextIndex]) ? trim((string)$row[$translatedTextIndex]) : null;

                // HARD FIX: repair mojibake if present (only needed for CSV files)
                // Excel files store text as Unicode, so this fix won't match for XLSX/XLS files
                if ($translatedText && preg_match('/Ã.|â./', $translatedText)) {
                    $translatedText = mb_convert_encoding($translatedText, 'UTF-8', 'Windows-1252');
                }

                // Skip rows with empty translated_text
                if (empty($translatedText)) {
                    $stats['skipped']++;
                    if (count($stats['errors']) < 100) { // Limit error details to prevent memory issues
                        $stats['errors'][] = [
                            'row' => $rowNumber,
                            'question_id' => $questionId,
                            'language' => $languageInput,
                            'error' => 'translated_text is empty',
                        ];
                    }
                    continue;
                }

                // Validate question_id exists (using pre-loaded set)
                if (empty($questionId) || !is_numeric($questionId) || !isset($questionSet[$questionId])) {
                    $stats['failed']++;
                    if (count($stats['errors']) < 100) {
                        $stats['errors'][] = [
                            'row' => $rowNumber,
                            'question_id' => $questionId,
                            'language' => $languageInput,
                            'error' => empty($questionId) || !is_numeric($questionId) 
                                ? 'question_id is required and must be numeric'
                                : 'question_id does not exist',
                        ];
                    }
                    continue;
                }

                // Validate language exists and is active (using pre-loaded map)
                if (empty($languageInput)) {
                    $stats['failed']++;
                    if (count($stats['errors']) < 100) {
                        $stats['errors'][] = [
                            'row' => $rowNumber,
                            'question_id' => $questionId,
                            'language' => $languageInput,
                            'error' => 'language is required',
                        ];
                    }
                    continue;
                }

                $languageKey = strtolower(trim($languageInput));
                $language = $languageMap[$languageKey] ?? null;

                if (!$language) {
                    $stats['failed']++;
                    if (count($stats['errors']) < 100) {
                        $stats['errors'][] = [
                            'row' => $rowNumber,
                            'question_id' => $questionId,
                            'language' => $languageInput,
                            'error' => 'language name does not exist or is not active',
                        ];
                    }
                    continue;
                }

                // Add to chunk for batch processing
                $chunk[] = [
                    'question_id' => (int)$questionId,
                    'language_id' => $language->id,
                    'translated_text' => $translatedText,
                    'row_number' => $rowNumber,
                ];

                // Process chunk when it reaches the chunk size
                if (count($chunk) >= $chunkSize) {
                    $this->processChunk($chunk, $stats);
                    $chunk = [];
                }
            }

            // Process remaining rows in chunk
            if (!empty($chunk)) {
                $this->processChunk($chunk, $stats);
            }

            // Determine status
            $status = 'completed';
            if ($stats['failed'] > 0 && $stats['inserted'] + $stats['updated'] == 0) {
                $status = 'failed';
            } elseif ($stats['failed'] > 0) {
                $status = 'partial';
            }

            // Log import history
            $importRecord = QuestionTranslationImport::create([
                'user_id' => $currentUser?->id,
                'file_name' => $fileName,
                'total_rows' => $totalRows,
                'inserted' => $stats['inserted'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'failed' => $stats['failed'],
                'errors' => count($stats['errors']) > 100 
                    ? array_slice($stats['errors'], 0, 100) + ['_truncated' => 'Only first 100 errors shown']
                    : $stats['errors'],
                'status' => $status,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Import completed',
                'data' => array_merge($stats, [
                    'import_id' => $importRecord->id,
                    'total_rows' => $totalRows,
                ]),
            ], 200);

        } catch (\Exception $e) {
            // Log failed import if record was created
            if ($importRecord) {
                $importRecord->update([
                    'status' => 'failed',
                    'notes' => 'Exception: ' . $e->getMessage(),
                ]);
            }

            \Log::error('Question translation import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $fileName,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error processing file: ' . $e->getMessage(),
                'data' => $stats,
            ], 500);
        }
    }

    /**
     * Process a chunk of translation data with database transaction
     * Prevents duplicate inserts using updateOrCreate
     */
    protected function processChunk(array $chunk, array &$stats)
    {
        DB::transaction(function () use ($chunk, &$stats) {
            foreach ($chunk as $item) {
                try {
                    // Use updateOrCreate to prevent duplicates
                    // This ensures one translation per (question_id + language_id) combination
                    $translation = QuestionTranslation::updateOrCreate(
                        [
                            'question_id' => $item['question_id'],
                            'language_id' => $item['language_id'],
                        ],
                        [
                            'translated_text' => $item['translated_text'],
                            'is_active' => true,
                        ]
                    );

                    // Track if it was created or updated
                    if ($translation->wasRecentlyCreated) {
                        $stats['inserted']++;
                    } else {
                        $stats['updated']++;
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    if (count($stats['errors']) < 100) {
                        $stats['errors'][] = [
                            'row' => $item['row_number'],
                            'question_id' => $item['question_id'],
                            'language_id' => $item['language_id'],
                            'error' => 'Failed to save translation: ' . $e->getMessage(),
                        ];
                    }
                }
            }
        });
    }

    /**
     * Update a question translation
     */
    public function updateTranslation(Request $request, $translationId)
    {
        $currentUser = $request->user();
        $hasAuthToken = $request->bearerToken() || $request->hasHeader('Authorization');

        // Check admin access if authenticated
        if ($hasAuthToken && $currentUser && $currentUser->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden - Admin access required'
            ], 403);
        }

        $translation = QuestionTranslation::with(['question', 'language'])->find($translationId);

        if (!$translation) {
            return response()->json([
                'status' => false,
                'message' => 'Translation not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'translated_text' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $translation->update($request->only(['translated_text', 'is_active']));

        return response()->json([
            'status' => true,
            'message' => 'Translation updated successfully',
            'data' => [
                'id' => $translation->id,
                'question_id' => $translation->question_id,
                'language_id' => $translation->language_id,
                'language_name' => $translation->language->name ?? null,
                'language_code' => $translation->language->code ?? null,
                'translated_text' => $translation->translated_text,
                'is_active' => $translation->is_active,
                'question' => [
                    'id' => $translation->question->id ?? null,
                    'question_text' => $translation->question->question_text ?? null,
                ],
            ],
        ], 200);
    }

    /**
     * Delete a question translation
     */
    public function deleteTranslation(Request $request, $translationId)
    {
        $currentUser = $request->user();
        $hasAuthToken = $request->bearerToken() || $request->hasHeader('Authorization');

        // Check admin access if authenticated
        if ($hasAuthToken && $currentUser && $currentUser->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden - Admin access required'
            ], 403);
        }

        $translation = QuestionTranslation::with(['question', 'language'])->find($translationId);

        if (!$translation) {
            return response()->json([
                'status' => false,
                'message' => 'Translation not found',
            ], 404);
        }

        $translationData = [
            'id' => $translation->id,
            'question_id' => $translation->question_id,
            'language_name' => $translation->language->name ?? null,
            'translated_text' => $translation->translated_text,
        ];

        $translation->delete();

        return response()->json([
            'status' => true,
            'message' => 'Translation deleted successfully',
            'data' => $translationData,
        ], 200);
    }
}
