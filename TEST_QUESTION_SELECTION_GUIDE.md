# Test Question Selection Guide

## 🎯 Overview

This feature allows you to **automatically select specific numbers of questions** from each cluster based on category (P, R, SDB). Instead of including all questions, you can set rules like:
- **5 P questions** (Positive)
- **5 R questions** (Reverse)  
- **2 SDB questions** (Social Desirability Bias)

From each cluster, and the system will randomly pick them for you!

---

## 📊 How It Works

### Step 1: Create Test with Clusters
Create a test and attach clusters with category counts.

### Step 2: Set Category Counts
For each cluster, specify how many questions you want from each category.

### Step 3: Generate Selection
The system automatically picks questions based on your rules.

### Step 4: Get Selected Questions
Retrieve only the selected questions (not all available questions).

---

## 🚀 API Endpoints

### 1. Create Test with Category Counts

**Endpoint:** `POST /api/tests`

**Body:**
```json
{
  "title": "Leadership Assessment",
  "description": "Test description",
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

**Response:**
```json
{
  "status": true,
  "message": "Test created successfully",
  "data": {
    "id": 1,
    "title": "Leadership Assessment",
    "clusters": [
      {
        "id": 1,
        "name": "Leadership",
        "pivot": {
          "p_count": 5,
          "r_count": 5,
          "sdb_count": 2
        }
      }
    ]
  }
}
```

---

### 2. Set Category Counts for a Cluster

**Endpoint:** `PUT /api/tests/{testId}/clusters/{clusterId}/category-counts`

**Body:**
```json
{
  "p_count": 5,
  "r_count": 5,
  "sdb_count": 2
}
```

**Example:**
```
PUT /api/tests/1/clusters/1/category-counts
```

**Response:**
```json
{
  "status": true,
  "message": "Category counts updated successfully",
  "data": { ... }
}
```

---

### 3. Generate Question Selection

**Endpoint:** `POST /api/tests/{id}/generate-questions`

**Example:**
```
POST /api/tests/1/generate-questions
```

**Response:**
```json
{
  "status": true,
  "message": "Question selection generated successfully",
  "data": {
    "test_id": 1,
    "selected_count": 12,
    "total_requested": 12
  }
}
```

**If insufficient questions:**
```json
{
  "status": true,
  "message": "Question selection generated with warnings",
  "data": {
    "test_id": 1,
    "selected_count": 10,
    "total_requested": 12
  },
  "warnings": [
    "Cluster 'Leadership': Only 3 P questions available, requested 5"
  ]
}
```

---

### 4. Regenerate Question Selection

**Endpoint:** `POST /api/tests/{id}/regenerate-questions`

Same as generate, but clears existing selection first and picks new random questions.

---

### 5. Get Selected Questions

**Endpoint:** `GET /api/tests/{id}/questions`

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "question_text": "I am a good leader",
      "category": "P",
      "pivot": {
        "cluster_id": 1,
        "order_no": 1
      }
    },
    {
      "id": 2,
      "question_text": "I avoid taking responsibility",
      "category": "R",
      "pivot": {
        "cluster_id": 1,
        "order_no": 2
      }
    }
  ],
  "message": "Selected questions fetched successfully",
  "count": 12
}
```

---

### 6. Get Test with Selected Questions

**Endpoint:** `GET /api/tests/{id}`

**Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "title": "Leadership Assessment",
    "clusters": [
      {
        "id": 1,
        "name": "Leadership",
        "pivot": {
          "p_count": 5,
          "r_count": 5,
          "sdb_count": 2
        }
      }
    ],
    "selected_questions": [
      { ... }
    ],
    "selected_questions_count": 12
  }
}
```

---

## 📝 Complete Workflow Example

### Step 1: Create Test
```json
POST /api/tests
{
  "title": "Personality Test",
  "clusters": [
    {
      "cluster_id": 1,
      "p_count": 5,
      "r_count": 5,
      "sdb_count": 2
    }
  ]
}
```

### Step 2: Generate Questions
```
POST /api/tests/1/generate-questions
```

### Step 3: Get Selected Questions
```
GET /api/tests/1/questions
```

**Result:** You get exactly 12 questions (5 P + 5 R + 2 SDB) from cluster 1.

---

## 🎲 How Selection Works

1. **For each cluster** in the test:
   - Gets all active questions from that cluster's constructs
   - Groups them by category (P, R, SDB)
   - Randomly shuffles each category
   - Picks the requested number from each category

2. **Prevents duplicates:** Same question won't be added twice

3. **Handles shortages:** If not enough questions available, picks what's available and warns you

4. **Order:** Questions are assigned `order_no` sequentially

---

## ⚠️ Important Notes

1. **Category counts are per cluster** - Each cluster can have different counts
2. **Questions are randomly selected** - Each generation may pick different questions
3. **Only active questions** are considered (`is_active = true`)
4. **Questions must belong to constructs** within the cluster
5. **Regenerate** to get a new random selection

---

## 🔄 Update Category Counts

### Method 1: Update when attaching cluster
```json
POST /api/tests/1/clusters/attach
{
  "clusters": [
    {
      "cluster_id": 2,
      "p_count": 3,
      "r_count": 3,
      "sdb_count": 1
    }
  ]
}
```

### Method 2: Update existing cluster
```json
PUT /api/tests/1/clusters/1/category-counts
{
  "p_count": 6,
  "r_count": 4,
  "sdb_count": 2
}
```

Then regenerate:
```
POST /api/tests/1/regenerate-questions
```

---

## ✅ Best Practices

1. **Set counts before generating** - Set all category counts first, then generate once
2. **Check warnings** - Review warnings if questions are insufficient
3. **Regenerate if needed** - Use regenerate to get different random selections
4. **Verify selection** - Check selected questions count matches your expectations

---

## 🎯 Example: Multiple Clusters

```json
POST /api/tests
{
  "title": "Comprehensive Assessment",
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

**Result:** 
- From Cluster 1: 5 P + 5 R + 2 SDB = **12 questions**
- From Cluster 2: 3 P + 3 R + 1 SDB = **7 questions**
- **Total: 19 questions** in the test

---

Happy Testing! 🎉

