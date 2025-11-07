# Postman Guide - Excel Upload Testing

## 📋 Quick Setup for Excel Upload in Postman

### Step 1: Get Authentication Token

First, login to get your Bearer token:

**Request:**
- **Method**: `POST`
- **URL**: `https://api.glansadesigns.com/strengthscompass/api/login`
- **Headers**: 
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body** (raw JSON):
  ```json
  {
    "email": "your@email.com",
    "password": "yourpassword"
  }
  ```

**Copy the `token` from the response!**

---

### Step 2: Prepare Your Excel File

Create an Excel file (.xlsx, .xls, or .csv) with this format:

| question_text | category | order_no | is_active |
|--------------|----------|----------|-----------|
| I enjoy leading teams | P | 1 | true |
| I prefer to follow others | R | 2 | true |
| I take initiative in projects | P | 3 | true |

**OR** if you want construct_id in the file:

| construct_id | question_text | category | order_no | is_active |
|-------------|---------------|----------|----------|-----------|
| 1 | I enjoy leading teams | P | 1 | true |
| 1 | I prefer to follow others | R | 2 | true |

---

### Step 3: Setup Postman Request

#### Option A: With Construct ID (Recommended)

**Request Configuration:**

1. **Method**: `POST`
2. **URL**: 
   ```
   https://api.glansadesigns.com/strengthscompass/api/questions/bulk-upload
   ```
   Or for local testing:
   ```
   http://localhost:8000/api/questions/bulk-upload
   ```

3. **Headers Tab**:
   ```
   Authorization: Bearer YOUR_TOKEN_HERE
   Accept: application/json
   ```
   ⚠️ **Important**: Do NOT set `Content-Type` manually - Postman will set it automatically for file uploads!

4. **Body Tab**:
   - Select **`form-data`** (NOT raw or x-www-form-urlencoded)
   - Add two keys:
   
   **Key 1:**
   - Key: `file`
   - Type: Change from "Text" to **"File"** (dropdown on the right)
   - Value: Click "Select Files" and choose your Excel file
   
   **Key 2:**
   - Key: `construct_id`
   - Type: Keep as "Text"
   - Value: Enter the construct ID (e.g., `1`)

5. **Send** the request!

#### Option B: Without Construct ID (Construct ID in Excel)

**Request Configuration:**

1. **Method**: `POST`
2. **URL**: Same as above
3. **Headers**: Same as above
4. **Body Tab**:
   - Select **`form-data`**
   - Add only one key:
   
   **Key:**
   - Key: `file`
   - Type: **"File"**
   - Value: Select your Excel file (must have `construct_id` column)

5. **Send** the request!

---

## 📸 Visual Guide

### Postman Setup Screenshot Description:

```
┌─────────────────────────────────────────┐
│ POST /api/questions/bulk-upload         │
├─────────────────────────────────────────┤
│ [Headers] [Body] [Pre-request] [Tests]│
├─────────────────────────────────────────┤
│ Body Tab (Selected)                      │
│ ○ none  ○ form-data  ○ x-www-form...    │
│                                         │
│ Key          │ Value      │ Type        │
│──────────────┼────────────┼─────────────│
│ file         │ [Select...]│ File ▼      │
│ construct_id │ 1          │ Text ▼      │
└─────────────────────────────────────────┘
```

---

## ✅ Expected Success Response

```json
{
  "status": true,
  "message": "Bulk upload completed",
  "data": {
    "success_count": 3,
    "failure_count": 0,
    "total_processed": 3
  }
}
```

---

## ⚠️ Expected Error Responses

### Missing File
```json
{
  "status": false,
  "errors": {
    "file": ["The file field is required."]
  },
  "message": "Validation failed"
}
```

### Invalid File Type
```json
{
  "status": false,
  "errors": {
    "file": ["The file must be a file of type: xlsx, xls, csv."]
  },
  "message": "Validation failed"
}
```

### File Too Large
```json
{
  "status": false,
  "errors": {
    "file": ["The file may not be greater than 10240 kilobytes."]
  },
  "message": "Validation failed"
}
```

### Invalid Construct ID
```json
{
  "status": false,
  "errors": {
    "construct_id": ["The selected construct id is invalid."]
  },
  "message": "Validation failed"
}
```

### Partial Success (Some rows failed)
```json
{
  "status": true,
  "message": "Bulk upload completed",
  "data": {
    "success_count": 2,
    "failure_count": 1,
    "total_processed": 3,
    "failures": [
      {
        "row": 3,
        "attribute": "category",
        "errors": ["The category must be P, R, or SDB"],
        "values": {
          "question_text": "Test question",
          "category": "INVALID",
          "order_no": 3
        }
      }
    ],
    "errors": [...]
  }
}
```

---

## 🎯 Step-by-Step Checklist

- [ ] Login and get Bearer token
- [ ] Create Excel file with correct format
- [ ] Open Postman
- [ ] Set method to POST
- [ ] Enter URL: `/api/questions/bulk-upload`
- [ ] Go to Headers tab
- [ ] Add: `Authorization: Bearer YOUR_TOKEN`
- [ ] Add: `Accept: application/json`
- [ ] Go to Body tab
- [ ] Select `form-data`
- [ ] Add key `file` (Type: File) → Select Excel file
- [ ] Add key `construct_id` (Type: Text) → Enter ID (optional)
- [ ] Click Send
- [ ] Check response

---

## 💡 Tips

1. **File Size**: Maximum 10MB
2. **File Types**: Only `.xlsx`, `.xls`, or `.csv`
3. **Headers**: Don't manually set `Content-Type` - Postman handles it
4. **First Row**: Must be headers (column names)
5. **Category Values**: Must be exactly `P`, `R`, or `SDB` (case-insensitive)
6. **Construct ID**: Either in request OR in Excel file (not both required)

---

## 🔍 Troubleshooting

### "The file field is required"
- Make sure you selected `form-data` in Body tab
- Make sure the key is named exactly `file` (lowercase)
- Make sure Type is set to "File" not "Text"

### "401 Unauthorized"
- Check your Bearer token is valid
- Make sure token hasn't expired (login again if needed)

### "404 Not Found"
- Check the URL is correct
- Make sure routes are deployed on server
- Clear route cache: `php artisan route:clear`

### "500 Server Error"
- Check Laravel logs: `storage/logs/laravel.log`
- Make sure `maatwebsite/excel` package is installed
- Make sure PHP extensions (gd, zip) are enabled

---

## 📝 Example Excel File Content

Save this as `questions.xlsx`:

**Sheet 1:**
```
question_text                    | category | order_no | is_active
I enjoy leading teams            | P        | 1        | true
I prefer to follow others        | R        | 2        | true
I take initiative in projects    | P        | 3        | true
I avoid making decisions         | R        | 4        | true
I motivate team members          | P        | 5        | true
```

---

## 🚀 Quick Test Command (cURL Alternative)

If you prefer command line:

```bash
curl -X POST "https://api.glansadesigns.com/strengthscompass/api/questions/bulk-upload" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "file=@/path/to/questions.xlsx" \
  -F "construct_id=1"
```

---

Happy Testing! 🎉

