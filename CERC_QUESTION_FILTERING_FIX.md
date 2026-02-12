# CERC Question Filtering Issue - Analysis & Fix

## Problem
Questions that were already answered in SC Pro tests are still appearing in CERC tests. This happens because:

1. **Different Question IDs for Same Text**: When questions are imported from Excel for both SC Pro and CERC tests, they get different database IDs even if the question text is identical.
   - Example: SC Pro has question ID `1` with text "What is your strength?"
   - CERC has question ID `100` with text "What is your strength?" (same text, different ID)
   - The old filtering logic only checked question IDs, so it wouldn't filter out question `100` even though it has the same text as question `1`.

2. **Frontend Fallback Logic**: The frontend has a fallback mechanism that merges with the `/questions` endpoint, which could reintroduce filtered questions.

## Root Cause Analysis

### Backend (✅ Fixed)
The backend filtering logic in `TestTakingController@getTestForUser` is working correctly:
- It identifies answered questions from SC Pro test results
- It filters them out from the CERC test's question list
- It logs the filtering process for debugging

### Frontend Issue (⚠️ Needs Fix)
The frontend has a fallback mechanism that can reintroduce filtered questions:

1. **Direct Path (Should Work Now)**
   ```javascript
   if (takeQuestions.length > 0 && takeOptions.length > 0) {
     // Use filtered questions directly
     setQuestions(takeQuestions);
     return; // Exit early
   }
   ```
   - **Issue**: `takeOptions` might not have been recognized as an array
   - **Fix Applied**: Backend now formats options as a proper array with `id`, `label`, `value`

2. **Fallback Path (Potential Issue)**
   When the direct path doesn't work, the frontend:
   - Extracts `testQuestionIds` from `/take` response (these are filtered)
   - Fetches ALL questions from `/questions` endpoint
   - Maps filtered IDs to questions from `/questions`
   - **Problem**: If the mapping fails or if there's a logic error, unfiltered questions might be included

## Backend Fixes Applied

### 1. **Text-Based Question Filtering** (Primary Fix)
Updated filtering logic to match questions by **question text** (normalized) in addition to question ID:

```php
// Get answered questions with their text
$answeredQuestions = UserAnswer::where('test_result_id', $scProTestResult->id)
    ->with('question:id,question_text')
    ->get();

// Create normalized set of answered question texts
$answeredQuestionTexts = $answeredQuestions->map(function ($userAnswer) {
    if ($userAnswer->question) {
        return $this->normalizeQuestionText($userAnswer->question->question_text);
    }
    return null;
})->filter()->unique()->toArray();

// Filter CERC questions by both ID and text
foreach ($selectedQuestions as $question) {
    $shouldExclude = false;
    
    // Check by question ID (exact match)
    if (in_array($question->id, $answeredQuestionIds)) {
        $shouldExclude = true;
    }
    // Check by question text (normalized comparison)
    elseif (!empty($answeredQuestionTexts)) {
        $normalizedCercQuestionText = $this->normalizeQuestionText($question->question_text);
        if (in_array($normalizedCercQuestionText, $answeredQuestionTexts)) {
            $shouldExclude = true;
        }
    }
    
    if (!$shouldExclude) {
        $filteredQuestions->push($question);
    }
}
```

**Helper Method:**
```php
private function normalizeQuestionText($questionText)
{
    if (empty($questionText)) {
        return '';
    }
    // Trim whitespace and normalize to lowercase for case-insensitive comparison
    return mb_strtolower(trim($questionText), 'UTF-8');
}
```

### 2. **Updated Answer Combination Logic**
Updated `combineAnswersForTest()` to also match by question text when combining SC Pro and CERC answers for reports:

```php
// Create maps for both ID and text-based matching
$scProAnswersByText = [];
$scProAnswersById = [];

foreach ($scProAnswers as $scProAnswer) {
    // Store by ID for exact matches
    $scProAnswersById[$scProAnswer->question_id] = $scProAnswer;
    
    // Store by normalized text for text-based matching
    $normalizedText = $this->normalizeQuestionText($scProAnswer->question->question_text);
    if (!isset($scProAnswersByText[$normalizedText])) {
        $scProAnswersByText[$normalizedText] = $scProAnswer;
    }
}

// Match CERC questions to SC Pro answers by both ID and text
```

### 3. Formatted Options Response
```php
// Before: Raw Eloquent collection
$options = OptionsModel::orderBy('value')->get();

// After: Formatted array
$options = OptionsModel::orderBy('value')->get()->map(function ($option) {
    return [
        'id' => $option->id,
        'label' => $option->label,
        'value' => $option->value,
    ];
})->values();
```

### 4. Added Filtering Flag
```php
$responseData = [
    // ... other fields
    'questions_filtered' => $questionsFiltered, // Indicates if filtering was applied
];
```

## Frontend Fixes Needed

### Option 1: Use Direct Path (Recommended)
Ensure the direct path is always used when filtered questions are available:

```javascript
const fetchTestData = async (testIdToFetch = null, userIdToPass = null) => {
  // ... existing code ...
  
  const [testResponse, questionsResponse] = await Promise.all([
    apiClient.get(`/tests/${idToUse}/take`, { params: takeParams }),
    axios.get(`${API_BASE_URL}/questions`, { headers: questionsHeaders })
  ]);

  const data = testResponse.data?.data;
  const takeQuestions = Array.isArray(data?.questions) ? data.questions : [];
  const takeOptions = Array.isArray(data?.options) ? data.options : [];
  
  // ✅ IMPROVED: Check if questions are filtered (for CERC tests)
  const questionsFiltered = data?.questions_filtered === true;
  
  // ✅ IMPROVED: For CERC tests with filtered questions, ALWAYS use direct path
  if (takeQuestions.length > 0 && (takeOptions.length > 0 || questionsFiltered)) {
    setTestName(data?.test?.title || "Assessment Test");
    setQuestions(
      takeQuestions.map((q) => ({
        id: q.id,
        question_text: q.question_text || q.questionText || q.question || "",
        category: q.category || "",
        order_no: q.order_no ?? q.orderNo ?? 0,
        translations: q.translations || [],
      }))
    );
    
    if (takeOptions.length > 0) {
      const sortedOptions = takeOptions
        .map((o) => ({
          id: o.id,
          label: o.label || o.option_text || o.optionText || o.name || "",
          value: o.value !== undefined ? o.value : null,
        }))
        .sort((a, b) => {
          if (a.value != null && b.value != null) return a.value - b.value;
          return 0;
        });
      setOptions(sortedOptions);
    }
    
    setLoading(false);
    return; // ✅ Exit early - don't merge with /questions endpoint
  }
  
  // Fallback: Only use this if direct path didn't work
  // ... rest of fallback logic ...
};
```

### Option 2: Fix Fallback Logic
If you need to keep the fallback, ensure it respects filtered questions:

```javascript
// In fallback logic, when mapping questions:
let finalQuestions = [];
if (testQuestionIds.length > 0) {
  // ✅ CRITICAL: Only use question IDs from /take endpoint (these are filtered)
  // Do NOT merge with all questions from /questions endpoint
  const questionsMap = new Map();
  allQuestions.forEach((q) => {
    questionsMap.set(q.id, q);
  });

  // ✅ Only map questions that are in testQuestionIds (filtered list)
  finalQuestions = testQuestionIds
    .map((id) => questionsMap.get(id))
    .filter((q) => q !== undefined) // Remove any that don't exist
    .map((q) => ({
      id: q.id,
      question_text: q.question_text || q.questionText || q.question || "",
      category: q.category || "",
      order_no: q.order_no || q.orderNo || 0,
      translations: q.translations || [],
    }));
} else {
  // If no question IDs from /take, use questions directly from /take response
  // (This should not happen for CERC tests)
  finalQuestions = takeQuestions.map((q) => ({
    // ... mapping logic
  }));
}
```

## Verification Steps

1. **Check Backend Logs**
   ```bash
   tail -f storage/logs/laravel.log | grep "CERC Test Question Filtering"
   ```
   Look for:
   - `total_cerc_questions_before_filter`: Total questions in CERC test
   - `answered_question_ids`: Questions answered in SC Pro
   - `filtered_count`: Questions remaining after filtering

2. **Check Frontend Console**
   Add logging to see which path is taken:
   ```javascript
   console.log('CERC Test Data:', {
     takeQuestions: takeQuestions.length,
     takeOptions: takeOptions.length,
     questionsFiltered: data?.questions_filtered,
     testSource: data?.test?.source
   });
   ```

3. **Test Flow**
   - Complete an SC Pro test
   - Start a CERC test
   - Verify only new questions appear (not SC Pro questions)

## Expected Behavior

### SC Pro Test
- Shows all questions assigned to the test
- No filtering applied

### CERC Test (After SC Pro Completion)
- Shows ONLY questions that were NOT answered in SC Pro
- Questions already answered in SC Pro are filtered out
- Backend response includes `questions_filtered: true`

## Summary

**Backend**: ✅ **FIXED** - The core issue was filtering by question ID only. Now filtering works by:
1. **Question ID** (for exact matches)
2. **Question Text** (normalized, case-insensitive) - **This is the key fix**
3. Options are properly formatted
4. Filtering flag added to response

**Frontend**: ⚠️ Needs update - Ensure direct path is used for filtered questions, or fix fallback logic to respect filtered question IDs

## Key Changes

### Problem
- SC Pro and CERC tests have different question IDs for the same question text when imported from Excel
- Old filtering only checked IDs, so questions with same text but different IDs weren't filtered

### Solution
- Filter by **both** question ID and question text (normalized)
- Normalize text by trimming whitespace and converting to lowercase
- Apply same logic to both question filtering and answer combination

### Result
- Questions with same text (even if different IDs) are now correctly filtered out
- CERC tests only show truly new questions
- Reports correctly combine answers from SC Pro and CERC based on question text matching

