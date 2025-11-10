# Test Taking & Scoring Guide

## 📋 Overview

This guide explains how users can take tests, submit answers, and view their results. The system automatically calculates scores based on question categories (P, R, SDB).

---

## 🎯 Scoring Logic

### Question Categories:

1. **P (Positive)**: Direct scoring
   - Answer 1 → Score 1
   - Answer 2 → Score 2
   - Answer 3 → Score 3
   - Answer 4 → Score 4
   - Answer 5 → Score 5

2. **R (Reverse)**: Reverse scoring
   - Answer 1 → Score 5
   - Answer 2 → Score 4
   - Answer 3 → Score 3
   - Answer 4 → Score 2
   - Answer 5 → Score 1

3. **SDB (Social Desirability Bias)**: Direct scoring (but may be excluded from construct scores)
   - Same as P category
   - Used to detect if user is trying to appear favorable
   - If >70% of SDB questions have high scores (4-5), `sdb_flag` is set to `true`

### Options (Same for all questions):
- 1: Strongly Disagree
- 2: Disagree
- 3: Neutral
- 4: Agree
- 5: Strongly Agree

---

## 🚀 API Endpoints

### 1. Get Test with Questions (For User to Take)

**Endpoint:** `GET /api/tests/{testId}/take`

**Description:** Returns the test with all questions and options for the user to take.

**Response:**
```json
{
  "status": true,
  "data": {
    "test": {
      "id": 1,
      "title": "Leadership Assessment",
      "description": "Test for leadership skills"
    },
    "questions": [
      {
        "id": 1,
        "question_text": "I am a good leader",
        "category": "P",
        "order_no": 1,
        "construct_id": 1,
        "construct_name": "Leadership",
        "cluster_id": 1
      }
    ],
    "options": [
      {
        "id": 1,
        "label": "Strongly Disagree",
        "value": 1
      },
      {
        "id": 2,
        "label": "Disagree",
        "value": 2
      },
      {
        "id": 3,
        "label": "Neutral",
        "value": 3
      },
      {
        "id": 4,
        "label": "Agree",
        "value": 4
      },
      {
        "id": 5,
        "label": "Strongly Agree",
        "value": 5
      }
    ],
    "total_questions": 25
  },
  "message": "Test fetched successfully"
}
```

---

### 2. Submit Test Answers

**Endpoint:** `POST /api/tests/{testId}/submit`

**Description:** Submits user answers and automatically calculates scores.

**Request Body:**
```json
{
  "user_id": 1,
  "answers": [
    {
      "question_id": 1,
      "answer_value": 4
    },
    {
      "question_id": 2,
      "answer_value": 5
    },
    {
      "question_id": 3,
      "answer_value": 2
    }
  ]
}
```

**Response:**
```json
{
  "status": true,
  "message": "Test submitted successfully",
  "data": {
    "test_result_id": 1,
    "total_score": 85.5,
    "average_score": 3.42,
    "cluster_scores": {
      "Leadership": 82.5,
      "Communication": 88.0
    },
    "construct_scores": {
      "Team Management": 4.0,
      "Decision Making": 3.8
    },
    "sdb_flag": false,
    "total_questions_answered": 25
  }
}
```

**Validation:**
- `user_id`: Required, must exist in users table
- `answers`: Required array
- `answers.*.question_id`: Required, must exist in questions table
- `answers.*.answer_value`: Required, integer between 1-5

---

### 3. Get Test Result

**Endpoint:** `GET /api/test-results/{testResultId}`

**Description:** Get detailed test result with all answers.

**Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "test_id": 1,
    "total_score": 85.5,
    "average_score": 3.42,
    "cluster_scores": {
      "Leadership": 82.5,
      "Communication": 88.0
    },
    "construct_scores": {
      "Team Management": 4.0,
      "Decision Making": 3.8
    },
    "sdb_flag": false,
    "status": "completed",
    "created_at": "2025-11-10T10:00:00.000000Z",
    "test": {
      "id": 1,
      "title": "Leadership Assessment"
    },
    "user": {
      "id": 1,
      "name": "John Doe"
    },
    "answers": [
      {
        "id": 1,
        "question_id": 1,
        "answer_value": 4,
        "final_score": 4.0,
        "question": {
          "id": 1,
          "question_text": "I am a good leader",
          "category": "P"
        }
      }
    ]
  },
  "message": "Test result fetched successfully"
}
```

---

### 4. Get All Test Results for a User

**Endpoint:** `GET /api/users/{userId}/test-results`

**Description:** Get all test results for a specific user.

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "test_id": 1,
      "total_score": 85.5,
      "average_score": 3.42,
      "status": "completed",
      "created_at": "2025-11-10T10:00:00.000000Z",
      "test": {
        "id": 1,
        "title": "Leadership Assessment"
      }
    }
  ],
  "message": "User test results fetched successfully"
}
```

---

### 5. Get All Results for a Test

**Endpoint:** `GET /api/tests/{testId}/results`

**Description:** Get all test results for a specific test (admin view).

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "test_id": 1,
      "total_score": 85.5,
      "average_score": 3.42,
      "status": "completed",
      "created_at": "2025-11-10T10:00:00.000000Z",
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      }
    }
  ],
  "message": "Test results fetched successfully"
}
```

---

## 📊 Score Calculation Details

### Total Score
- Sum of all `final_score` values from answers where `include_in_construct = true`
- SDB questions may be excluded if `include_in_construct = false`

### Average Score
- `total_score / number_of_questions_included`

### Cluster Scores
- Average of all question scores within each cluster
- Only includes questions where `include_in_construct = true`

### Construct Scores
- Average of all question scores within each construct
- Only includes questions where `include_in_construct = true`

### SDB Flag
- Set to `true` if more than 70% of SDB questions have high scores (4 or 5)
- Indicates potential social desirability bias

---

## 🧪 Example Flow

### Step 1: User Gets Test
```
GET /api/tests/1/take
```
User sees test title, description, all questions, and options.

### Step 2: User Submits Answers
```
POST /api/tests/1/submit
{
  "user_id": 1,
  "answers": [
    {"question_id": 1, "answer_value": 4},
    {"question_id": 2, "answer_value": 5},
    ...
  ]
}
```
System calculates scores and returns results.

### Step 3: User Views Results
```
GET /api/test-results/1
```
User sees detailed breakdown of scores.

---

## 🔧 Scoring Rules (Optional)

You can create custom scoring rules in the `scoring_rules` table:

- `reverse_score`: Override default category behavior
- `weight`: Multiply score by weight (default 1.0)
- `include_in_construct`: Whether to include in construct/cluster scores (default true)

If no scoring rule exists, the system uses the question's category (P, R, SDB).

---

## 📝 Notes

1. **Options are the same for all questions** - No need to specify options per question
2. **SDB questions** - May be excluded from construct scores but still tracked
3. **Reverse scoring** - Automatically applied for R category questions
4. **Weight** - Can be used to give more importance to certain questions
5. **Transaction safety** - All answers are saved in a database transaction

---

## ✅ Next Steps

1. **Seed Options Table**: Make sure the options table has the 5 default options (1-5)
2. **Create Scoring Rules** (Optional): Add custom scoring rules if needed
3. **Test the Flow**: Use Postman to test the complete flow

---

Happy Testing! 🎉

