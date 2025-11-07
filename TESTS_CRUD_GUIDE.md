# Tests CRUD Guide

## 📋 Overview

Tests are assessments that contain questions from constructs. Each test can be associated with multiple clusters, and questions come from the constructs within those clusters.

**Relationship Flow:**
```
Test → Clusters (many-to-many) → Constructs → Questions
```

---

## 🗄️ Database Structure

### Tables Created:

1. **tests** - Main test table
   - `id`, `title`, `description`, `is_active`, `created_at`, `updated_at`

2. **test_cluster** - Pivot table linking tests to clusters
   - `id`, `test_id`, `cluster_id`, `created_at`

3. **clusters** - Updated with `short_code` field

---

## 🚀 API Endpoints

All endpoints require authentication (`Authorization: Bearer YOUR_TOKEN`)

### Basic CRUD

#### 1. Get All Tests
```
GET /api/tests
```
**Query Parameters:**
- `is_active` (optional): Filter by active status (true/false)

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "title": "Leadership Assessment",
      "description": "Test for leadership skills",
      "is_active": true,
      "clusters": [...]
    }
  ],
  "message": "Tests fetched successfully"
}
```

#### 2. Get Single Test
```
GET /api/tests/{id}
```
**Response includes:**
- Test details
- Associated clusters
- Constructs within clusters
- All questions from those constructs

#### 3. Create Test
```
POST /api/tests
```
**Body:**
```json
{
  "title": "Leadership Assessment",
  "description": "Test for leadership skills",
  "is_active": true,
  "cluster_ids": [1, 2, 3]
}
```
**Note:** `cluster_ids` is optional - you can attach clusters later

#### 4. Update Test
```
PUT /api/tests/{id}
```
**Body:**
```json
{
  "title": "Updated Title",
  "description": "Updated description",
  "is_active": false,
  "cluster_ids": [1, 2]  // This will replace all existing clusters
}
```

#### 5. Delete Test
```
DELETE /api/tests/{id}
```

---

### Cluster Management

#### 6. Attach Clusters to Test
```
POST /api/tests/{id}/clusters/attach
```
**Body:**
```json
{
  "cluster_ids": [1, 2, 3]
}
```
**Note:** This adds clusters without removing existing ones

#### 7. Detach Clusters from Test
```
POST /api/tests/{id}/clusters/detach
```
**Body:**
```json
{
  "cluster_ids": [1, 2]
}
```

---

### Get Related Data

#### 8. Get All Questions for a Test
```
GET /api/tests/{id}/questions
```
**Returns:** All questions from constructs within the test's clusters

#### 9. Get All Constructs for a Test
```
GET /api/tests/{id}/constructs
```
**Returns:** All constructs within the test's clusters

---

## 📝 Example Usage Flow

### Step 1: Create a Test
```json
POST /api/tests
{
  "title": "Personality Assessment",
  "description": "Comprehensive personality test",
  "is_active": true,
  "cluster_ids": [1, 2]
}
```

### Step 2: Get Test with Questions
```
GET /api/tests/1
```
This returns the test with all questions from constructs in clusters 1 and 2.

### Step 3: Add More Clusters
```json
POST /api/tests/1/clusters/attach
{
  "cluster_ids": [3]
}
```

### Step 4: Get Updated Questions
```
GET /api/tests/1/questions
```
Now includes questions from clusters 1, 2, and 3.

---

## 🎯 Postman Testing

### Create Test Example:
1. **Method**: `POST`
2. **URL**: `https://api.glansadesigns.com/strengthscompass/api/tests`
3. **Headers**:
   ```
   Authorization: Bearer YOUR_TOKEN
   Content-Type: application/json
   Accept: application/json
   ```
4. **Body** (raw JSON):
   ```json
   {
     "title": "Leadership Assessment",
     "description": "Test for leadership skills",
     "is_active": true,
     "cluster_ids": [1, 2]
   }
   ```

### Get Test with Questions:
1. **Method**: `GET`
2. **URL**: `https://api.glansadesigns.com/strengthscompass/api/tests/1`
3. **Headers**: Same as above

---

## 📊 Data Flow

When you create a test with clusters:
1. Test is created
2. Clusters are linked via `test_cluster` table
3. Questions are automatically available through:
   - Test → Clusters → Constructs → Questions

**Example:**
- Test "Leadership Assessment" has Cluster "Leadership"
- Cluster "Leadership" has Construct "Team Management"
- Construct "Team Management" has Questions
- All those questions are available in the test

---

## ✅ Next Steps

Once you share the tables for:
- Test-Question relationships (if questions need to be selected per test)
- Test-Option relationships (linking options to questions in tests)

I can implement those features as well!

---

## 🔍 Important Notes

1. **Questions are automatically included** from all constructs in the test's clusters
2. **Options are already available** (from options table) - we'll link them once you share the relationship table
3. **Clusters can be added/removed** dynamically without recreating the test
4. **Questions update automatically** when clusters are added/removed

---

Happy Testing! 🎉

