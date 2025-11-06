# Postman Guide - Constructs CRUD Testing

## 📋 Prerequisites

1. **Base URL**: Your Laravel API base URL (typically `http://localhost:8000/api` or your domain)
2. **Authentication**: All Construct CRUD endpoints require authentication token
3. **Postman Setup**: Install Postman if you haven't already

---

## 🔐 Step 1: Authentication (Login First)

Before testing Constructs CRUD, you need to authenticate and get a token.

### 1.1 Register a New User (Optional - if you don't have an account)

**Request:**
- **Method**: `POST`
- **URL**: `{{base_url}}/register`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body** (raw JSON):
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "admin"
  }
  ```

**Response:**
```json
{
  "data": {
    "user": { ... },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  },
  "status": 201,
  "message": "User registered successfully"
}
```

### 1.2 Login to Get Token

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
    "email": "john@example.com",
    "password": "password123"
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

**⚠️ IMPORTANT**: Copy the `token` value from the response. You'll need it for all Construct CRUD requests.

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

## 🏗️ Step 3: Constructs CRUD Operations

### 3.1 Get All Constructs

**Request:**
- **Method**: `GET`
- **URL**: `{{base_url}}/constructs`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```

**Optional Query Parameters:**
- `cluster_id`: Filter by cluster ID
  - Example: `{{base_url}}/constructs?cluster_id=1`

**Expected Response (200 OK):**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "cluster_id": 1,
      "name": "Leadership",
      "short_code": "LEAD",
      "description": "...",
      "definition": "...",
      "high_behavior": "...",
      "low_behavior": "...",
      "benefits": "...",
      "risks": "...",
      "coaching_applications": "...",
      "case_example": "...",
      "display_order": 1,
      "created_at": "2025-11-06T04:51:16.000000Z",
      "updated_at": "2025-11-06T04:51:16.000000Z",
      "cluster": {
        "id": 1,
        "name": "Cluster Name",
        "description": "..."
      }
    }
  ]
}
```

---

### 3.2 Get Constructs by Cluster ID (Alternative Route)

**Request:**
- **Method**: `GET`
- **URL**: `{{base_url}}/clusters/1/constructs`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Note**: Replace `1` with the actual cluster ID

**Expected Response (200 OK):**
Same format as 3.1

---

### 3.3 Get Single Construct

**Request:**
- **Method**: `GET`
- **URL**: `{{base_url}}/constructs/1`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Note**: Replace `1` with the actual construct ID

**Expected Response (200 OK):**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "cluster_id": 1,
    "name": "Leadership",
    "short_code": "LEAD",
    "description": "...",
    "definition": "...",
    "high_behavior": "...",
    "low_behavior": "...",
    "benefits": "...",
    "risks": "...",
    "coaching_applications": "...",
    "case_example": "...",
    "display_order": 1,
    "created_at": "2025-11-06T04:51:16.000000Z",
    "updated_at": "2025-11-06T04:51:16.000000Z",
    "cluster": {
      "id": 1,
      "name": "Cluster Name",
      "description": "..."
    }
  }
}
```

**Error Response (404 Not Found):**
```json
{
  "status": false,
  "message": "Construct not found"
}
```

---

### 3.4 Create New Construct

**Request:**
- **Method**: `POST`
- **URL**: `{{base_url}}/constructs`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Body** (raw JSON):
  ```json
  {
    "cluster_id": 1,
    "name": "Leadership",
    "short_code": "LEAD",
    "description": "The ability to guide and inspire others",
    "definition": "Leadership is the capacity to influence and guide others toward achieving common goals",
    "high_behavior": "Takes initiative, delegates effectively, motivates team members",
    "low_behavior": "Prefers to follow, avoids decision-making, reluctant to take charge",
    "benefits": "Better team performance, improved morale, clear direction",
    "risks": "May become controlling, burnout from over-responsibility",
    "coaching_applications": "Focus on building confidence, decision-making skills, communication",
    "case_example": "A manager who successfully led a team through a major project",
    "display_order": 1
  }
  ```

**Required Fields:**
- `cluster_id` (required, must exist in clusters table)
- `name` (required)

**Optional Fields:**
- `short_code`, `description`, `definition`, `high_behavior`, `low_behavior`, `benefits`, `risks`, `coaching_applications`, `case_example`, `display_order`

**Expected Response (201 Created):**
```json
{
  "status": true,
  "message": "Construct created successfully",
  "data": {
    "id": 1,
    "cluster_id": 1,
    "name": "Leadership",
    "short_code": "LEAD",
    ...
    "cluster": { ... }
  }
}
```

**Error Response (422 Validation Error):**
```json
{
  "status": false,
  "errors": {
    "cluster_id": ["The cluster id field is required."],
    "name": ["The name field is required."]
  }
}
```

**Error Response (422 Invalid Cluster ID):**
```json
{
  "status": false,
  "errors": {
    "cluster_id": ["The selected cluster id is invalid."]
  }
}
```

---

### 3.5 Update Construct

**Request:**
- **Method**: `PUT`
- **URL**: `{{base_url}}/constructs/1`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Note**: Replace `1` with the actual construct ID
- **Body** (raw JSON):
  ```json
  {
    "name": "Updated Leadership",
    "description": "Updated description",
    "display_order": 2
  }
  ```

**Note**: You can update any field. Use `sometimes` validation - only include fields you want to update.

**Expected Response (200 OK):**
```json
{
  "status": true,
  "message": "Construct updated successfully",
  "data": {
    "id": 1,
    "cluster_id": 1,
    "name": "Updated Leadership",
    "description": "Updated description",
    "display_order": 2,
    ...
    "cluster": { ... }
  }
}
```

**Error Response (404 Not Found):**
```json
{
  "status": false,
  "message": "Construct not found"
}
```

---

### 3.6 Delete Construct

**Request:**
- **Method**: `DELETE`
- **URL**: `{{base_url}}/constructs/1`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  Authorization: Bearer {{token}}
  ```
- **Note**: Replace `1` with the actual construct ID

**Expected Response (200 OK):**
```json
{
  "status": true,
  "message": "Construct deleted successfully"
}
```

**Error Response (404 Not Found):**
```json
{
  "status": false,
  "message": "Construct not found"
}
```

---

## 🎯 Quick Testing Checklist

1. ✅ Login and get token
2. ✅ Get all constructs (with and without cluster_id filter)
3. ✅ Get constructs by cluster ID
4. ✅ Get single construct
5. ✅ Create a new construct
6. ✅ Update the construct
7. ✅ Delete the construct

---

## 💡 Tips

1. **Save Token**: After login, copy the token and save it as an environment variable in Postman
2. **Use Variables**: Set up `{{base_url}}` and `{{token}}` as environment variables
3. **Test with Real Cluster ID**: Make sure you have at least one cluster created before testing constructs
4. **Check Console**: Use Postman Console (View → Show Postman Console) to see request/response details
5. **Collection**: Create a Postman Collection to organize all your requests

---

## 🔍 Troubleshooting

**401 Unauthorized:**
- Token is missing or invalid
- Make sure to include `Authorization: Bearer {{token}}` header
- Token might have expired - login again to get a new token

**422 Validation Error:**
- Check that `cluster_id` exists in the database
- Required fields are missing
- Check the error message for specific validation issues

**404 Not Found:**
- Construct ID doesn't exist
- Check that you're using the correct ID

**500 Server Error:**
- Check Laravel logs: `storage/logs/laravel.log`
- Make sure migrations have been run: `php artisan migrate`

---

## 📚 Additional Endpoints

### Get All Clusters (to find cluster IDs)
- **Method**: `GET`
- **URL**: `{{base_url}}/clusters`
- **Headers**: `Authorization: Bearer {{token}}`

---

## 🚀 Testing Flow Example

1. **Login** → Get token
2. **Get Clusters** → Find a cluster ID (e.g., `cluster_id: 1`)
3. **Create Construct** → Use the cluster ID from step 2
4. **Get All Constructs** → Verify the new construct appears
5. **Get Single Construct** → Use the ID from step 3 response
6. **Update Construct** → Modify some fields
7. **Get Single Construct** → Verify changes
8. **Delete Construct** → Remove the test construct

---

Happy Testing! 🎉

