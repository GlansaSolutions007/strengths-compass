# SC Pro and CERC Test Flow - Complete Guide

## Overview

The system now supports two test sources:
- **SC Pro**: The original test (90 questions)
- **CERC**: Additional test (80 questions total, includes some SC Pro questions)

**Complete Flow**: 
1. Admin creates tests with source differentiation
2. User takes SC Pro test first
3. User optionally takes CERC test (after SC Pro completion)
4. Reports generated for both tests

---

## Part 1: Admin - Creating Tests with Source

### Step 1: Create SC Pro Test

**Endpoint:** `POST /api/tests`

**Request Body:**
```json
{
  "title": "SC Pro Assessment",
  "description": "Strengths Compass Professional Assessment",
  "age_group_id": 1,
  "is_active": true,
  "source": "SC Pro",
  "clusters": [
    {
      "cluster_id": 1,
      "p_count": 5,
      "r_count": 3,
      "sdb_count": 2
    },
    {
      "cluster_id": 2,
      "p_count": 4,
      "r_count": 4,
      "sdb_count": 1
    }
  ]
}
```

**Alternative: Upload Questions via Excel**

**Endpoint:** `POST /api/tests`

**Request (multipart/form-data):**
- `title`: "SC Pro Assessment"
- `description`: "Strengths Compass Professional Assessment"
- `age_group_id`: 1
- `is_active`: true
- `source`: "SC Pro"
- `questions_file`: (Excel file with questions)

**Excel Format:**
| Cluster | Construct | Question | Category |
|---------|-----------|----------|---------|
| Leadership | Team Management | I am a good leader | P |
| Leadership | Decision Making | I make quick decisions | R |

**Response:**
```json
{
  "status": true,
  "message": "Test created successfully",
  "data": {
    "id": 1,
    "title": "SC Pro Assessment",
    "description": "Strengths Compass Professional Assessment",
    "source": "SC Pro",
    "age_group_id": 1,
    "is_active": true,
    "selected_questions_count": 90
  }
}
```

### Step 2: Create CERC Test

**Endpoint:** `POST /api/tests`

**Request Body:**
```json
{
  "title": "CERC Assessment",
  "description": "Comprehensive Enhanced Report with CERC",
  "age_group_id": 1,
  "is_active": true,
  "source": "CERC",
  "sc_pro_test_id": 1,  // REQUIRED: Maps this CERC test to the corresponding SC Pro test
  "clusters": [
    {
      "cluster_id": 1,  // Same cluster as SC Pro (overlapping)
      "p_count": 3,
      "r_count": 2,
      "sdb_count": 1
    },
    {
      "cluster_id": 2,  // Same cluster as SC Pro (overlapping)
      "p_count": 2,
      "r_count": 2,
      "sdb_count": 1
    },
    {
      "cluster_id": 5,  // New cluster (CERC only)
      "p_count": 4,
      "r_count": 3,
      "sdb_count": 2
    }
  ]
}
```

**Important Notes:**
- **`sc_pro_test_id` is REQUIRED** for CERC tests - This explicitly maps which SC Pro test this CERC test is linked to
- This allows multiple SC Pro and CERC tests per age group
- Each CERC test must be explicitly linked to one SC Pro test
- CERC test can include clusters from SC Pro (overlapping questions)
- CERC test can include new clusters (CERC-only questions)
- When questions are generated, CERC test will include questions from both SC Pro and CERC sources
- Total CERC test: 80 questions (mix of SC Pro + CERC questions)

**Why `sc_pro_test_id` is needed:**
- Multiple SC Pro tests can exist for the same age group (e.g., "SC Pro Basic", "SC Pro Advanced")
- Multiple CERC tests can exist for the same age group (e.g., "CERC Basic", "CERC Advanced")
- Without explicit mapping, the system wouldn't know which SC Pro test corresponds to which CERC test
- Example: If you have "SC Pro Basic" (ID: 1) and "SC Pro Advanced" (ID: 2), and "CERC Basic" (ID: 3) and "CERC Advanced" (ID: 4), you need to map:
  - CERC Basic (ID: 3) → `sc_pro_test_id: 1` (links to SC Pro Basic)
  - CERC Advanced (ID: 4) → `sc_pro_test_id: 2` (links to SC Pro Advanced)

**Response:**
```json
{
  "status": true,
  "message": "Test created successfully",
  "data": {
    "id": 2,
    "title": "CERC Assessment",
    "description": "Comprehensive Enhanced Report with CERC",
    "source": "CERC",
    "age_group_id": 1,
    "is_active": true,
    "selected_questions_count": 80
  }
}
```

### Step 3: Verify Tests Created

**Endpoint:** `GET /api/tests?source=SC Pro` or `GET /api/tests?source=CERC`

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "title": "SC Pro Assessment",
      "source": "SC Pro",
      "age_group_id": 1,
      "is_active": true,
      "sc_pro_test_id": null  // SC Pro tests don't have this
    },
    {
      "id": 2,
      "title": "CERC Assessment",
      "source": "CERC",
      "age_group_id": 1,
      "is_active": true,
      "sc_pro_test_id": 1  // Links to SC Pro test ID 1
    }
  ],
  "message": "Tests fetched successfully"
}
```

**Get Test with Relationships:**
```json
{
  "id": 2,
  "title": "CERC Assessment",
  "source": "CERC",
  "sc_pro_test_id": 1,
  "sc_pro_test": {  // Relationship loaded
    "id": 1,
    "title": "SC Pro Assessment",
    "source": "SC Pro"
  },
  "cerc_tests": []  // If this was an SC Pro test, would show linked CERC tests
}
```

### Step 4: Example - Multiple Tests per Age Group

**Scenario:** You have multiple SC Pro and CERC tests for the same age group.

**Create SC Pro Tests:**
```json
// SC Pro Basic
POST /api/tests
{
  "title": "SC Pro Basic Assessment",
  "source": "SC Pro",
  "age_group_id": 1
}

// SC Pro Advanced  
POST /api/tests
{
  "title": "SC Pro Advanced Assessment",
  "source": "SC Pro",
  "age_group_id": 1
}
```

**Create CERC Tests (with explicit mapping):**
```json
// CERC Basic - maps to SC Pro Basic
POST /api/tests
{
  "title": "CERC Basic Assessment",
  "source": "CERC",
  "age_group_id": 1,
  "sc_pro_test_id": 1  // Links to SC Pro Basic (ID: 1)
}

// CERC Advanced - maps to SC Pro Advanced
POST /api/tests
{
  "title": "CERC Advanced Assessment",
  "source": "CERC",
  "age_group_id": 1,
  "sc_pro_test_id": 2  // Links to SC Pro Advanced (ID: 2)
}
```

**Result:**
- User completes "SC Pro Basic" (ID: 1) → Can take "CERC Basic" (ID: 3, linked to ID: 1)
- User completes "SC Pro Advanced" (ID: 2) → Can take "CERC Advanced" (ID: 4, linked to ID: 2)
- Each CERC test knows exactly which SC Pro test it requires

---

## Part 2: User Flow - Taking Tests

### Flow Overview

1. **User takes SC Pro test** → Completes all 90 questions
2. **User agrees to take CERC test** → System checks eligibility
3. **User takes CERC test** → Only sees NEW questions (SC Pro questions already answered are filtered out)
4. **Reports generated**:
   - SC Pro Report: Based on SC Pro test answers
   - CERC Report: Based on combined SC Pro + CERC answers

---

## API Endpoints

### 1. Get Available Tests for User

**Endpoint:** `GET /api/tests/available`

**Query Parameters:**
- `user_id` (required): User ID
- `age_group_id` (optional): Filter by age group

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "title": "SC Pro Assessment",
      "description": "...",
      "source": "SC Pro",
      "age_group_id": 1,
      "is_completed": false,
      "can_take": true,
      "requires_sc_pro": false,
      "sc_pro_test": null
    },
    {
      "id": 2,
      "title": "CERC Assessment",
      "description": "...",
      "source": "CERC",
      "age_group_id": 1,
      "is_completed": false,
      "can_take": false,  // false if SC Pro not completed
      "requires_sc_pro": true,
      "sc_pro_test": {
        "id": 1,
        "title": "SC Pro Assessment",
        "is_completed": false,  // Check this to show/hide CERC test
        "test_result_id": null
      }
    }
  ],
  "message": "Available tests fetched successfully"
}
```

**Frontend Usage:**
- Show SC Pro test if `can_take: true` and `is_completed: false`
- Show CERC test option only if `sc_pro_test.is_completed: true`
- Disable CERC test if `can_take: false`

---

### 2. Check CERC Eligibility

**Endpoint:** `POST /api/tests/{testId}/check-cerc-eligibility`

**Body:**
```json
{
  "user_id": 1
}
```

**Response (Eligible):**
```json
{
  "status": true,
  "can_take_cerc": true,
  "message": "User is eligible to take CERC test",
  "sc_pro_test": {
    "id": 1,
    "title": "SC Pro Assessment",
    "description": "..."
  },
  "sc_pro_test_result": {
    "id": 10,
    "completed_at": "2025-02-11T10:00:00.000000Z"
  }
}
```

**Response (Not Eligible):**
```json
{
  "status": true,
  "can_take_cerc": false,
  "message": "User must complete SC Pro test first",
  "sc_pro_test": {
    "id": 1,
    "title": "SC Pro Assessment",
    "description": "..."
  },
  "sc_pro_test_result": null
}
```

**Frontend Usage:**
- Call this before showing CERC test to user
- Show message if `can_take_cerc: false`
- Redirect to SC Pro test if not eligible

---

### 3. Get Test for User (with Questions)

**Endpoint:** `GET /api/tests/{testId}/take?user_id={userId}&lang=en`

**Important:** For CERC tests, `user_id` is **required** in query parameters.

**Note:** For SC Pro tests, the response includes `available_cerc_tests` array if CERC tests are linked.

**Response (SC Pro Test):**
```json
{
  "status": true,
  "data": {
    "test": {
      "id": 1,
      "title": "SC Pro Assessment",
      "description": "...",
      "source": "SC Pro"
    },
    "questions": [...],  // All 90 questions
    "options": [...],
    "total_questions": 90,
    "available_cerc_tests": [  // Included if CERC tests are linked
      {
        "id": 2,
        "title": "CERC Assessment",
        "description": "...",
        "source": "CERC"
      }
    ]
  },
  "message": "Test fetched successfully"
}
```

**Response (CERC Test - if SC Pro not completed):**
```json
{
  "status": false,
  "message": "You must complete the SC Pro test before taking the CERC test.",
  "requires_sc_pro_completion": true,
  "sc_pro_test_id": 1,
  "sc_pro_test_title": "SC Pro Assessment"
}
```

**Response (CERC Test - if eligible):**
```json
{
  "status": true,
  "data": {
    "test": {
      "id": 2,
      "title": "CERC Assessment",
      "description": "...",
      "source": "CERC"
    },
    "questions": [...],  // Only NEW questions (SC Pro questions filtered out)
    "options": [...],
    "total_questions": 45  // Only new CERC questions
  },
  "message": "Test fetched successfully"
}
```

**Frontend Usage:**
- Always include `user_id` in query params for CERC tests
- Handle 403/422 errors and show appropriate message
- Redirect to SC Pro test if needed

---

### 4. Submit Test Answers

**Endpoint:** `POST /api/tests/{testId}/submit`

**Body:**
```json
{
  "user_id": 1,
  "is_consent": true,
  "answers": [
    {
      "question_id": 1,
      "answer_value": 4
    },
    ...
  ]
}
```

**Response:**
```json
{
  "status": true,
  "message": "Test submitted successfully",
  "data": {
    "test_result_id": 15,
    "total_score": 85.5,
    "average_score": 3.42,
    "average_percentage": 64,
    "overall_category": "medium",
    "cluster_scores": {...},
    "construct_scores": {...},
    "sdb_flag": false,
    "radar_chart": {...},
    "total_questions_answered": 45
  }
}
```

**Note:** For CERC tests, the system automatically:
- Combines SC Pro answers (for overlapping questions)
- Uses new CERC answers (for new questions)
- Calculates report based on combined answers

---

### 5. Get User Test Results (All)

**Endpoint:** `GET /api/users/{userId}/test-results`

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "test_result_id": 15,
      "test": {
        "id": 2,
        "title": "CERC Assessment",
        "source": "CERC"
      },
      "scores": {...},
      "radar_chart": {...},
      "status": "completed",
      "submitted_at": "2025-02-11T12:00:00.000000Z"
    },
    {
      "test_result_id": 10,
      "test": {
        "id": 1,
        "title": "SC Pro Assessment",
        "source": "SC Pro"
      },
      "scores": {...},
      "radar_chart": {...},
      "status": "completed",
      "submitted_at": "2025-02-11T10:00:00.000000Z"
    }
  ],
  "message": "User test results fetched successfully"
}
```

---

### 6. Get User Test Results Grouped by Source

**Endpoint:** `GET /api/users/{userId}/test-results/by-source`

**Response:**
```json
{
  "status": true,
  "data": {
    "sc_pro": {
      "has_completed": true,
      "latest_result": {
        "test_result_id": 10,
        "test": {
          "id": 1,
          "title": "SC Pro Assessment",
          "source": "SC Pro"
        },
        "scores": {...},
        "radar_chart": {...},
        "submitted_at": "2025-02-11T10:00:00.000000Z"
      },
      "all_results": [...],
      "total_completed": 1
    },
    "cerc": {
      "has_completed": true,
      "can_take": true,
      "latest_result": {
        "test_result_id": 15,
        "test": {
          "id": 2,
          "title": "CERC Assessment",
          "source": "CERC"
        },
        "scores": {...},
        "radar_chart": {...},
        "submitted_at": "2025-02-11T12:00:00.000000Z"
      },
      "all_results": [...],
      "total_completed": 1
    }
  },
  "message": "User test results by source fetched successfully"
}
```

**Frontend Usage:**
- Use this to show two separate report sections
- Show SC Pro report if `sc_pro.has_completed: true`
- Show CERC report if `cerc.has_completed: true`
- Show "Take CERC Test" button if `sc_pro.has_completed: true` and `cerc.has_completed: false`

---

## Frontend Implementation Flow

### Complete Flow with Popup

```javascript
// Step 1: User takes SC Pro test
const scProTestId = 1;
const userId = 123;

// Get SC Pro test (includes CERC test info)
const testResponse = await fetch(`/api/tests/${scProTestId}/take?user_id=${userId}`);
const testData = await testResponse.json();

// Store CERC test info for later
let availableCercTests = testData.data.available_cerc_tests || [];

// User answers questions and submits
const submitResponse = await fetch(`/api/tests/${scProTestId}/submit`, {
  method: 'POST',
  body: JSON.stringify({
    user_id: userId,
    is_consent: true,
    answers: userAnswers
  })
});

const submitData = await submitResponse.json();

// Step 2: Show popup if CERC tests are available
if (submitData.data.has_cerc_tests && submitData.data.available_cerc_tests) {
  const cercTests = submitData.data.available_cerc_tests;
  
  // Show popup
  const userWantsCerc = await showCercTestPopup(cercTests);
  
  if (userWantsCerc) {
    // User selected a CERC test
    const selectedCercTestId = userWantsCerc.testId;
    
    // Navigate to CERC test
    navigateToCercTest(selectedCercTestId, userId);
  } else {
    // User declined, show SC Pro results
    showTestResults(submitData.data.test_result_id);
  }
}

function showCercTestPopup(cercTests) {
  return new Promise((resolve) => {
    // Show modal/popup
    const modal = document.createElement('div');
    modal.innerHTML = `
      <div class="popup-overlay">
        <div class="popup-content">
          <h2>Would you like to take the CERC test?</h2>
          <p>You've completed the SC Pro test. Would you like to take the additional CERC assessment?</p>
          ${cercTests.length > 1 ? `
            <select id="cercTestSelect">
              ${cercTests.map(test => `
                <option value="${test.id}">${test.title}</option>
              `).join('')}
            </select>
          ` : ''}
          <div class="popup-buttons">
            <button onclick="handleCercPopupResponse(true)">Yes, Take CERC Test</button>
            <button onclick="handleCercPopupResponse(false)">No, Show Results</button>
          </div>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    window.handleCercPopupResponse = (wantsCerc) => {
      document.body.removeChild(modal);
      if (wantsCerc) {
        const testId = cercTests.length > 1 
          ? document.getElementById('cercTestSelect').value 
          : cercTests[0].id;
        resolve({ testId: parseInt(testId) });
      } else {
        resolve(null);
      }
    };
  });
}

function navigateToCercTest(cercTestId, userId) {
  // Navigate to CERC test page
  window.location.href = `/test/${cercTestId}?user_id=${userId}`;
}
```

### Step 1: Dashboard/Test Selection Page

```javascript
// Get available tests
const response = await fetch(`/api/tests/available?user_id=${userId}`);
const { data: tests } = await response.json();

// Find SC Pro and CERC tests
const scProTest = tests.find(t => t.source === 'SC Pro');
const cercTest = tests.find(t => t.source === 'CERC');

// Show SC Pro test
if (scProTest && !scProTest.is_completed) {
  // Show "Take SC Pro Test" button
}

// Show CERC test (only if SC Pro completed)
if (cercTest && cercTest.sc_pro_test?.is_completed) {
  // Show "Take CERC Test" button
} else if (cercTest && !cercTest.sc_pro_test?.is_completed) {
  // Show "Complete SC Pro first" message (disabled button)
}
```

### Step 2: Taking SC Pro Test

```javascript
// 1. Get test questions (includes linked CERC test info)
const response = await fetch(`/api/tests/${scProTestId}/take?user_id=${userId}`);
const { data } = await response.json();

// Response includes available CERC tests if any
if (data.available_cerc_tests && data.available_cerc_tests.length > 0) {
  console.log('CERC tests available after SC Pro:', data.available_cerc_tests);
  // Store CERC test IDs for later use
  const cercTestIds = data.available_cerc_tests.map(t => t.id);
}

// 2. User answers questions
// 3. Submit answers
const submitResponse = await fetch(`/api/tests/${scProTestId}/submit`, {
  method: 'POST',
  body: JSON.stringify({
    user_id: userId,
    is_consent: true,
    answers: userAnswers
  })
});

const submitData = await submitResponse.json();

// 4. After submission, check if CERC tests are available
if (submitData.data.has_cerc_tests && submitData.data.available_cerc_tests) {
  // Show popup asking if user wants to take CERC test
  const cercTests = submitData.data.available_cerc_tests;
  
  // Show popup with CERC test options
  showCercTestPopup(cercTests);
}

function showCercTestPopup(cercTests) {
  // Display popup: "Would you like to take the CERC test?"
  // Show list of available CERC tests
  // User can select which CERC test to take (if multiple)
  // On "Yes" → Navigate to CERC test
  // On "No" → Close popup and show results
}
```

### Step 3: Taking CERC Test (After SC Pro Completion)

**Option A: Using CERC test ID from SC Pro submission response**

```javascript
// After SC Pro test submission, user clicks "Yes" on popup
// Use the CERC test ID from the submission response
const cercTestId = submitData.data.available_cerc_tests[0].id; // Or let user select

// 1. Check eligibility (optional but recommended)
const eligibilityCheck = await fetch(`/api/tests/${cercTestId}/check-cerc-eligibility`, {
  method: 'POST',
  body: JSON.stringify({ user_id: userId })
});

const { can_take_cerc } = await eligibilityCheck.json();

if (!can_take_cerc) {
  // Show error message and redirect to SC Pro
  return;
}

// 2. Get CERC test questions (user_id is REQUIRED)
const response = await fetch(`/api/tests/${cercTestId}/take?user_id=${userId}`);
const { data } = await response.json();

// Note: Questions will be filtered - only new questions shown
// 3. User answers questions
// 4. Submit answers
await fetch(`/api/tests/${cercTestId}/submit`, {
  method: 'POST',
  body: JSON.stringify({
    user_id: userId,
    is_consent: true,
    answers: userAnswers
  })
});
```

**Option B: Get CERC tests for a specific SC Pro test**

```javascript
// Get all CERC tests linked to the SC Pro test
const response = await fetch(`/api/tests/${scProTestId}/cerc-tests`);
const { data } = await response.json();

// data.cerc_tests contains all CERC tests linked to this SC Pro test
if (data.cerc_tests.length > 0) {
  // Show user the available CERC tests
  // Let them select which one to take
  const selectedCercTest = data.cerc_tests[0]; // Or user selection
  
  // Navigate to CERC test
  navigateToTest(selectedCercTest.id);
}
```

### Step 4: Viewing Reports

```javascript
// Get results grouped by source
const response = await fetch(`/api/users/${userId}/test-results/by-source`);
const { data } = await response.json();

// Show SC Pro Report
if (data.sc_pro.has_completed) {
  const scProResultId = data.sc_pro.latest_result.test_result_id;
  // Navigate to: /api/test-results/${scProResultId}/report
}

// Show CERC Report
if (data.cerc.has_completed) {
  const cercResultId = data.cerc.latest_result.test_result_id;
  // Navigate to: /api/test-results/${cercResultId}/report
}
```

---

## Key Points for Frontend

1. **Always check eligibility** before showing CERC test
2. **Always include `user_id`** in query params when fetching CERC test
3. **Handle errors gracefully** - 403/422 responses indicate SC Pro not completed
4. **Show clear messaging** - "Complete SC Pro test first" when CERC is disabled
5. **Use grouped results endpoint** - Better for showing two separate reports
6. **Filter questions automatically** - Backend handles filtering for CERC tests
7. **Reports are separate** - SC Pro report and CERC report are independent

---

## Error Handling

### Error: CERC Test Requires SC Pro Completion

**Status:** 403 Forbidden

**Response:**
```json
{
  "status": false,
  "message": "You must complete the SC Pro test before taking the CERC test.",
  "requires_sc_pro_completion": true,
  "sc_pro_test_id": 1,
  "sc_pro_test_title": "SC Pro Assessment"
}
```

**Frontend Action:**
- Show error message
- Optionally redirect to SC Pro test
- Disable CERC test button

### Error: Missing user_id for CERC Test

**Status:** 422 Unprocessable Entity

**Response:**
```json
{
  "status": false,
  "message": "user_id is required for CERC tests. Please provide user_id to check eligibility.",
  "requires_sc_pro_completion": true
}
```

**Frontend Action:**
- Always include `user_id` in query params for CERC tests

---

---

## Part 3: Reports - Viewing Test Results

### Step 1: Get User Test Results Grouped by Source

**Endpoint:** `GET /api/users/{userId}/test-results/by-source`

**Response:**
```json
{
  "status": true,
  "data": {
    "sc_pro": {
      "has_completed": true,
      "latest_result": {
        "test_result_id": 10,
        "test": {
          "id": 1,
          "title": "SC Pro Assessment",
          "source": "SC Pro"
        },
        "scores": {
          "total_score": 320.5,
          "average_score": 3.56,
          "average_percentage": 64,
          "cluster_scores": {...},
          "construct_scores": {...}
        },
        "radar_chart": {...},
        "submitted_at": "2025-02-11T10:00:00.000000Z"
      },
      "all_results": [...],
      "total_completed": 1
    },
    "cerc": {
      "has_completed": true,
      "can_take": true,
      "latest_result": {
        "test_result_id": 15,
        "test": {
          "id": 2,
          "title": "CERC Assessment",
          "source": "CERC"
        },
        "scores": {
          "total_score": 285.3,
          "average_score": 3.57,
          "average_percentage": 64,
          "cluster_scores": {...},  // Based on combined SC Pro + CERC answers
          "construct_scores": {...}   // Based on combined SC Pro + CERC answers
        },
        "radar_chart": {...},
        "submitted_at": "2025-02-11T12:00:00.000000Z"
      },
      "all_results": [...],
      "total_completed": 1
    }
  },
  "message": "User test results by source fetched successfully"
}
```

### Step 2: View SC Pro Report

**Endpoint:** `GET /api/test-results/{testResultId}/report`

**Example:** `GET /api/test-results/10/report`

**Response:**
```json
{
  "status": true,
  "data": {
    "test_result_id": 10,
    "test": {
      "id": 1,
      "title": "SC Pro Assessment",
      "source": "SC Pro"
    },
    "user": {...},
    "scores": {
      "total_score": 320.5,
      "average_score": 3.56,
      "average_percentage": 64,
      "overall_category": "medium"
    },
    "cluster_scores": {...},
    "construct_scores": {...},
    "radar_chart": {...},
    "report_summary": "...",
    "recommendations": "..."
  }
}
```

**Download PDF:**
- `GET /api/test-results/10/report/pdf` - Full report PDF
- `GET /api/test-results/10/report/pdf/short` - Short report PDF

### Step 3: View CERC Report

**Endpoint:** `GET /api/test-results/{testResultId}/report`

**Example:** `GET /api/test-results/15/report`

**Important:** The CERC report is calculated using:
- SC Pro answers (for overlapping questions)
- CERC answers (for new questions)
- Combined scores and analysis

**Response:**
```json
{
  "status": true,
  "data": {
    "test_result_id": 15,
    "test": {
      "id": 2,
      "title": "CERC Assessment",
      "source": "CERC"
    },
    "user": {...},
    "scores": {
      "total_score": 285.3,
      "average_score": 3.57,
      "average_percentage": 64,
      "overall_category": "medium"
    },
    "cluster_scores": {...},  // Calculated from combined answers
    "construct_scores": {...}, // Calculated from combined answers
    "radar_chart": {...},
    "report_summary": "...",
    "recommendations": "..."
  }
}
```

**Download PDF:**
- `GET /api/test-results/15/report/pdf` - Full CERC report PDF
- `GET /api/test-results/15/report/pdf/short` - Short CERC report PDF

### Step 4: Frontend Report Display

```javascript
// Get results grouped by source
const response = await fetch(`/api/users/${userId}/test-results/by-source`);
const { data } = await response.json();

// Display SC Pro Report Section
if (data.sc_pro.has_completed) {
  const scProResultId = data.sc_pro.latest_result.test_result_id;
  
  // Option 1: Fetch report data
  const scProReport = await fetch(`/api/test-results/${scProResultId}/report`);
  
  // Option 2: Direct link to PDF
  const scProPdfUrl = `/api/test-results/${scProResultId}/report/pdf`;
  
  // Display SC Pro report
  renderReport(data.sc_pro.latest_result, 'SC Pro');
}

// Display CERC Report Section
if (data.cerc.has_completed) {
  const cercResultId = data.cerc.latest_result.test_result_id;
  
  // Option 1: Fetch report data
  const cercReport = await fetch(`/api/test-results/${cercResultId}/report`);
  
  // Option 2: Direct link to PDF
  const cercPdfUrl = `/api/test-results/${cercResultId}/report/pdf`;
  
  // Display CERC report
  renderReport(data.cerc.latest_result, 'CERC');
}
```

---

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN - Test Creation                     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  1. Create SC Pro Test              │
        │     - source: "SC Pro"               │
        │     - 90 questions                  │
        │     - Clusters & Constructs         │
        └─────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  2. Create CERC Test                │
        │     - source: "CERC"                │
        │     - sc_pro_test_id: 1 (REQUIRED)  │
        │     - 80 questions (mix)             │
        │     - Includes SC Pro clusters       │
        │     - Includes new CERC clusters     │
        └─────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    USER - Test Taking                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  3. User Views Available Tests       │
        │     GET /api/tests/available         │
        │     - Shows SC Pro (can_take: true)  │
        │     - Shows CERC (can_take: false)    │
        └─────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  4. User Takes SC Pro Test           │
        │     GET /api/tests/1/take           │
        │     - All 90 questions shown        │
        │     - User answers all questions     │
        │     POST /api/tests/1/submit        │
        │     - TestResult created            │
        └─────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  5. User Checks CERC Eligibility    │
        │     POST /api/tests/2/check-cerc... │
        │     - can_take_cerc: true            │
        └─────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  6. User Takes CERC Test             │
        │     GET /api/tests/2/take?user_id=X │
        │     - Only NEW questions shown       │
        │     - SC Pro questions filtered out  │
        │     - User answers new questions     │
        │     POST /api/tests/2/submit         │
        │     - TestResult created             │
        │     - Combines SC Pro + CERC answers │
        └─────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    REPORTS - Viewing Results                 │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  7. Get User Results by Source      │
        │     GET /api/users/X/test-results/  │
        │         by-source                   │
        │     - sc_pro.has_completed: true    │
        │     - cerc.has_completed: true      │
        └─────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  8. View SC Pro Report               │
        │     GET /api/test-results/10/report  │
        │     - Based on SC Pro answers only  │
        │     - 90 questions analyzed          │
        └─────────────────────────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────┐
        │  9. View CERC Report                 │
        │     GET /api/test-results/15/report  │
        │     - Based on combined answers      │
        │     - SC Pro answers (overlapping)   │
        │     - CERC answers (new questions)   │
        │     - 80 questions analyzed           │
        └─────────────────────────────────────┘
```

---

## Test Mapping - SC Pro to CERC

### How Mapping Works

The system uses **explicit mapping** via the `sc_pro_test_id` field to link CERC tests to their corresponding SC Pro tests.

**Key Points:**
1. **`sc_pro_test_id` is REQUIRED** when creating CERC tests
2. Each CERC test must explicitly reference one SC Pro test
3. This allows multiple SC Pro and CERC tests per age group
4. System validates that `sc_pro_test_id` references an actual SC Pro test

**Example Mapping:**
```
Age Group 1:
  - SC Pro Basic (ID: 1)
  - SC Pro Advanced (ID: 2)
  - CERC Basic (ID: 3) → sc_pro_test_id: 1
  - CERC Advanced (ID: 4) → sc_pro_test_id: 2

Age Group 2:
  - SC Pro Standard (ID: 5)
  - CERC Standard (ID: 6) → sc_pro_test_id: 5
```

**Validation Rules:**
- `sc_pro_test_id` must exist in tests table
- `sc_pro_test_id` must reference a test with `source: "SC Pro"`
- A test cannot reference itself
- If `source: "CERC"` is set, `sc_pro_test_id` is required

**Fallback Behavior:**
- If `sc_pro_test_id` is not set (for backward compatibility), system falls back to finding SC Pro test by `age_group_id`
- However, **explicit mapping is recommended** for clarity and to support multiple tests

---

## Summary

### Admin Side:
- ✅ Create SC Pro test with `source: "SC Pro"`
- ✅ Create CERC test with `source: "CERC"`
- ✅ CERC test can include SC Pro clusters (overlapping questions)
- ✅ CERC test can include new clusters (CERC-only questions)

### User Side:
- ✅ SC Pro test: User can take anytime
- ✅ CERC test: Only available after SC Pro completion
- ✅ CERC test questions: Automatically filtered (only new questions shown)
- ✅ System validates eligibility before allowing CERC test

### Reports:
- ✅ SC Pro Report: Based on SC Pro test answers only (90 questions)
- ✅ CERC Report: Based on combined SC Pro + CERC answers (80 questions total)
- ✅ Two separate reports: SC Pro report and CERC report are independent
- ✅ CERC report automatically combines answers from both tests

### Technical:
- ✅ All endpoints include `source` field for easy identification
- ✅ Automatic question filtering for CERC tests
- ✅ Automatic answer combination for CERC reports
- ✅ Clear error messages for frontend handling

