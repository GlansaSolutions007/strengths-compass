# Postman Guide - Test Creation with Automatic Question Generation

## 📋 Prerequisites

1. **Base URL**: Your Laravel API base URL
   - Local: `http://localhost:8000/api`
   - Production: `https://api.glansadesigns.com/strengthscompass/api`
2. **Authentication**: All Test endpoints require authentication token
3. **Postman Setup**: Install Postman if you haven't already

---

## 🔐 Step 1: Authentication (Login First)

Before testing, you need to authenticate and get a token.

### 1.1 Login to Get Token

**Request:**
- **Method**: `POST`
- **URL**: `{{base_url}}/login`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body** (raw JSON):
  ```json
  {
    "email": "your-email@example.com",
    "password": "your-password"
  }
  ```

**Response:**
```json
{
  "data": {
    "user": { ... },
    "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  },
  "status": 200,
  "message": "Login successful"
}
```

**⚠️ IMPORTANT**: Copy the `token` value from the response. You'll need it for all Test requests.

---

## 📝 Step 2: Setup Postman Environment (Recommended)

1. Click **"Environments"** in the left sidebar
2. Click **"+"** to create a new environment
3. Add variables:
   - `base_url`: `http://localhost:8000/api` (or your API URL)
   - `token`: (leave empty, will be set after login)
4. Save the environment
5. Select the environment from the dropdown

---

## 🧪 Step 3: Test Creation with Automatic Question Generation

### 3.1 Create Test with cluster_ids (Simple Format)

This will automatically include **ALL questions** from the specified clusters.

**Request:**
- **Method**: `POST`
- **URL**: `{{base_url}}/tests`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Body** (raw JSON):
  ```json
  {
    "title": "Leadership Assessment",
    "description": "Test for leadership skills",
    "is_active": true,
    "cluster_ids": [1, 2]
  }
  ```

**Expected Response (201 Created):**
```json
{
  "status": true,
  "message": "Test created successfully",
  "data": {
    "id": 1,
    "title": "Leadership Assessment",
    "description": "Test for leadership skills",
    "is_active": true,
    "created_at": "2025-11-07T10:00:00.000000Z",
    "updated_at": "2025-11-07T10:00:00.000000Z",
    "clusters": [
      {
        "id": 1,
        "name": "Cluster 1",
        "pivot": {
          "test_id": 1,
          "cluster_id": 1,
          "p_count": null,
          "r_count": null,
          "sdb_count": null
        }
      },
      {
        "id": 2,
        "name": "Cluster 2",
        "pivot": {
          "test_id": 1,
          "cluster_id": 2,
          "p_count": null,
          "r_count": null,
          "sdb_count": null
        }
      }
    ]
  }
}
```

**✅ What Happened:**
- Test was created
- Clusters 1 and 2 were attached
- **Questions were automatically generated and stored** (all questions from clusters 1 and 2)

---

### 3.2 Verify Questions Were Generated

**Request:**
- **Method**: `GET`
- **URL**: `{{base_url}}/tests/1`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Note**: Replace `1` with the test ID from step 3.1

**Expected Response (200 OK):**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "title": "Leadership Assessment",
    "description": "Test for leadership skills",
    "is_active": true,
    "clusters": [...],
    "selected_questions": [
      {
        "id": 1,
        "construct_id": 1,
        "question_text": "Question 1",
        "category": "P",
        "order_no": 1,
        "is_active": true,
        "pivot": {
          "test_id": 1,
          "question_id": 1,
          "cluster_id": 1,
          "order_no": 1
        }
      },
      {
        "id": 2,
        "construct_id": 1,
        "question_text": "Question 2",
        "category": "R",
        "order_no": 2,
        "pivot": {
          "test_id": 1,
          "question_id": 2,
          "cluster_id": 1,
          "order_no": 2
        }
      }
      // ... more questions
    ],
    "selected_questions_count": 25
  },
  "message": "Test fetched successfully"
}
```

**✅ Check:**
- `selected_questions` array contains all questions from clusters 1 and 2
- `selected_questions_count` shows the total number of questions
- Questions are automatically ordered by `order_no`

---

### 3.3 Get Questions Only

**Request:**
- **Method**: `GET`
- **URL**: `{{base_url}}/tests/1/questions`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```

**Expected Response (200 OK):**
```json
{
  "status": true,
  "data": {
    "test_id": 1,
    "questions": [
      {
        "id": 1,
        "question_text": "Question 1",
        "category": "P",
        "order_no": 1,
        "cluster_id": 1
      }
      // ... more questions
    ],
    "total_count": 25
  },
  "message": "Questions fetched successfully"
}
```

---

## 🎯 Step 4: Create Test with Category Counts (Advanced)

If you want to specify how many questions from each category (P, R, SDB):

**Request:**
- **Method**: `POST`
- **URL**: `{{base_url}}/tests`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Body** (raw JSON):
  ```json
  {
    "title": "Custom Assessment",
    "description": "Test with specific question counts",
    "is_active": true,
    "clusters": [
      {
        "cluster_id": 1,
        "p_count": 5,
        "r_count": 5,
        "sdb_count": 2
      },
      {
        "cluster_id": 2,
        "p_count": 3,
        "r_count": 3,
        "sdb_count": 1
      }
    ]
  }
  ```

**Expected Response (201 Created):**
```json
{
  "status": true,
  "message": "Test created successfully",
  "data": {
    "id": 2,
    "title": "Custom Assessment",
    "clusters": [
      {
        "id": 1,
        "pivot": {
          "p_count": 5,
          "r_count": 5,
          "sdb_count": 2
        }
      },
      {
        "id": 2,
        "pivot": {
          "p_count": 3,
          "r_count": 3,
          "sdb_count": 1
        }
      }
    ]
  }
}
```

**✅ What Happened:**
- Test was created
- Clusters were attached with specific category counts
- **Questions were automatically generated**:
  - From Cluster 1: 5 P + 5 R + 2 SDB = 12 questions
  - From Cluster 2: 3 P + 3 R + 1 SDB = 7 questions
  - **Total: 19 questions** (automatically selected and stored)

---

## 📊 Step 5: Complete Testing Flow

### Test Scenario: Create Test and Verify Everything Works

1. **Create Test with cluster_ids**
   ```
   POST {{base_url}}/tests
   {
     "title": "My Test",
     "cluster_ids": [1, 2]
   }
   ```
   - ✅ Note the `id` from response (e.g., `id: 3`)

2. **Get Test Details**
   ```
   GET {{base_url}}/tests/3
   ```
   - ✅ Verify `selected_questions` array is populated
   - ✅ Verify `selected_questions_count` > 0

3. **Get Questions Only**
   ```
   GET {{base_url}}/tests/3/questions
   ```
   - ✅ Verify questions are returned

4. **Get All Tests**
   ```
   GET {{base_url}}/tests
   ```
   - ✅ Verify your test appears in the list

---

## 🔍 Step 6: Troubleshooting

### Issue: Questions Not Appearing

**Check:**
1. Do the clusters have constructs?
   ```
   GET {{base_url}}/clusters/1
   ```
2. Do the constructs have questions?
   ```
   GET {{base_url}}/constructs/1
   ```
3. Are the questions active?
   - Questions must have `is_active: true`

### Issue: 401 Unauthorized

**Solution:**
- Make sure you're including the `Authorization: Bearer {{token}}` header
- Token might be expired - login again to get a new token

### Issue: 422 Validation Error

**Common Causes:**
- Missing `title` field
- Invalid `cluster_ids` (cluster doesn't exist)
- Invalid JSON format

**Example Error:**
```json
{
  "status": false,
  "errors": {
    "title": ["The title field is required."],
    "cluster_ids.0": ["The selected cluster_ids.0 is invalid."]
  },
  "message": "Validation failed"
}
```

---

## 📝 Quick Reference

### All Test Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tests` | Get all tests |
| `GET` | `/api/tests/{id}` | Get single test with questions |
| `POST` | `/api/tests` | Create test (auto-generates questions) |
| `PUT` | `/api/tests/{id}` | Update test |
| `DELETE` | `/api/tests/{id}` | Delete test |
| `GET` | `/api/tests/{id}/questions` | Get test questions |
| `GET` | `/api/tests/{id}/constructs` | Get test constructs |
| `POST` | `/api/tests/{id}/clusters/attach` | Attach more clusters |
| `POST` | `/api/tests/{id}/clusters/detach` | Detach clusters |
| `POST` | `/api/tests/{id}/generate-questions` | Regenerate questions |
| `PUT` | `/api/tests/{testId}/clusters/{clusterId}/category-counts` | Set category counts |

---

## ✅ Summary

**Key Points:**
1. ✅ Questions are **automatically generated** when you create a test with `cluster_ids`
2. ✅ If no category counts are set, **ALL questions** from the clusters are included
3. ✅ If category counts are set, only the specified number of questions are selected
4. ✅ Questions are stored in the `test_question` table automatically
5. ✅ You can verify questions using `GET /api/tests/{id}` or `GET /api/tests/{id}/questions`

**Happy Testing! 🎉**

