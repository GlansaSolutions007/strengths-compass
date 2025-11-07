# Excel Bulk Upload Guide - Questions

## 📋 Overview

The bulk upload feature allows you to upload multiple questions at once via Excel file. It supports **two upload modes**:

### Mode 1: Select Construct First (Recommended)
- Admin selects a construct from dropdown
- Uploads Excel file with questions for that construct
- All questions in the file will be assigned to the selected construct

### Mode 2: Construct ID in Excel
- Excel file contains a `construct_id` column
- Each row can have a different construct_id
- Useful for uploading questions for multiple constructs in one file

---

## 🚀 API Endpoint

**POST** `/api/questions/bulk-upload`

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: multipart/form-data
Accept: application/json
```

**Request Body (Form Data):**
- `file` (required): Excel file (.xlsx, .xls, or .csv)
- `construct_id` (optional): ID of the construct (if Mode 1)

---

## 📊 Excel File Format

### Required Columns

| Column Name | Description | Required | Example |
|------------|-------------|----------|---------|
| `question_text` or `question` | The question text | ✅ Yes | "I enjoy leading teams" |
| `category` | Question category | ✅ Yes | P, R, or SDB |
| `order_no` or `order` | Display order | ✅ Yes | 1, 2, 3... |
| `construct_id` | Construct ID | ⚠️ Only if Mode 2 | 1, 2, 3... |
| `is_active` | Active status | ❌ No (default: true) | true, false, 1, 0, yes, no |

### Category Values
- **P** = Positive
- **R** = Reverse
- **SDB** = Social Desirability Bias

### Example Excel File (Mode 1 - Construct Selected First)

| question_text | category | order_no | is_active |
|--------------|----------|----------|-----------|
| I enjoy leading teams | P | 1 | true |
| I prefer to follow others | R | 2 | true |
| I take initiative in group projects | P | 3 | true |
| I avoid making decisions | R | 4 | true |
| I motivate team members effectively | P | 5 | true |

### Example Excel File (Mode 2 - Construct ID in File)

| construct_id | question_text | category | order_no | is_active |
|-------------|---------------|----------|----------|-----------|
| 1 | I enjoy leading teams | P | 1 | true |
| 1 | I prefer to follow others | R | 2 | true |
| 2 | I am good at problem solving | P | 1 | true |
| 2 | I struggle with complex issues | R | 2 | true |
| 1 | I take initiative | P | 3 | true |

---

## 📝 Step-by-Step Guide

### Frontend Implementation

#### Option 1: Select Construct First (Recommended UX)

```javascript
// Step 1: Admin selects construct
<select name="construct_id" v-model="selectedConstructId">
  <option value="">Select a Construct</option>
  <option v-for="construct in constructs" :key="construct.id" :value="construct.id">
    {{ construct.name }}
  </option>
</select>

// Step 2: Admin uploads Excel file
<input type="file" @change="handleFileUpload" accept=".xlsx,.xls,.csv" />

// Step 3: Submit
const formData = new FormData();
formData.append('file', selectedFile);
formData.append('construct_id', selectedConstructId); // From dropdown

axios.post('/api/questions/bulk-upload', formData, {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'multipart/form-data'
  }
})
```

#### Option 2: Direct Upload with Construct ID in File

```javascript
// Admin just uploads file (no construct selection needed)
<input type="file" @change="handleFileUpload" accept=".xlsx,.xls,.csv" />

const formData = new FormData();
formData.append('file', selectedFile);
// No construct_id - it's in the Excel file

axios.post('/api/questions/bulk-upload', formData, {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'multipart/form-data'
  }
})
```

---

## ✅ Success Response

```json
{
  "status": true,
  "message": "Bulk upload completed",
  "data": {
    "success_count": 10,
    "failure_count": 0,
    "total_processed": 10
  }
}
```

---

## ⚠️ Partial Success Response (Some rows failed)

```json
{
  "status": true,
  "message": "Bulk upload completed",
  "data": {
    "success_count": 8,
    "failure_count": 2,
    "total_processed": 10,
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
    "errors": [
      {
        "row": {...},
        "error": "Invalid category: INVALID. Must be P, R, or SDB"
      }
    ]
  }
}
```

---

## ❌ Error Responses

### Validation Error
```json
{
  "status": false,
  "errors": {
    "file": ["The file field is required."]
  },
  "message": "Validation failed"
}
```

### Construct Not Found
```json
{
  "status": false,
  "message": "Construct not found"
}
```

### File Processing Error
```json
{
  "status": false,
  "message": "Error processing Excel file: [error details]"
}
```

---

## 🎯 Best Practices

### For Frontend Developers:

1. **Show Construct Selection First (Mode 1)**
   - Better UX - admin knows which construct they're uploading for
   - Prevents mistakes
   - Simpler Excel file format

2. **Provide Excel Template Download**
   - Create a downloadable template with correct headers
   - Include example rows
   - Show validation rules

3. **Show Upload Progress**
   - Display progress bar during upload
   - Show processing status

4. **Display Results Clearly**
   - Show success count prominently
   - List failures with row numbers
   - Allow admin to download error report

5. **Validation Before Upload**
   - Check file type
   - Check file size (max 10MB)
   - Preview first few rows if possible

### Excel File Tips:

1. **First row must be headers** (column names)
2. **Use exact column names** (case-insensitive but spelling matters)
3. **Category must be exactly**: P, R, or SDB
4. **Order numbers should be unique** per construct
5. **Construct ID must exist** in database (if using Mode 2)

---

## 📥 Sample Excel Template

You can create a template file with these headers:

**Mode 1 Template (No construct_id column):**
```
question_text | category | order_no | is_active
```

**Mode 2 Template (With construct_id column):**
```
construct_id | question_text | category | order_no | is_active
```

---

## 🔍 Testing in Postman

1. **Set Method**: POST
2. **URL**: `https://api.glansadesigns.com/strengthscompass/api/questions/bulk-upload`
3. **Headers**:
   - `Authorization: Bearer YOUR_TOKEN`
   - `Accept: application/json`
4. **Body**: 
   - Select `form-data`
   - Add key `file` (type: File) → Select your Excel file
   - Add key `construct_id` (type: Text) → Enter construct ID (optional)
5. **Send Request**

---

## 🛠️ Troubleshooting

### Common Issues:

1. **"Construct ID is required"**
   - Solution: Either provide `construct_id` in request or include it in Excel file

2. **"Invalid category"**
   - Solution: Category must be exactly P, R, or SDB (case-insensitive)

3. **"Construct ID does not exist"**
   - Solution: Verify construct_id exists in database

4. **"The file field is required"**
   - Solution: Make sure file is uploaded with key name `file`

5. **"File too large"**
   - Solution: Maximum file size is 10MB

---

## 📊 Recommended Frontend Flow

```
┌─────────────────────────────────────┐
│  1. Admin selects Construct         │
│     (from dropdown)                 │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  2. Admin clicks "Upload Excel"     │
│     (file picker opens)              │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  3. Admin selects Excel file         │
│     (validates file type/size)       │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  4. Show preview/validation          │
│     (optional - show first 5 rows)    │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  5. Submit to API                    │
│     POST /api/questions/bulk-upload  │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  6. Display Results                 │
│     - Success count                  │
│     - Failure list (if any)          │
│     - Download error report          │
└─────────────────────────────────────┘
```

---

## 💡 Example Frontend Code (Vue.js)

```vue
<template>
  <div>
    <!-- Construct Selection -->
    <select v-model="selectedConstructId" required>
      <option value="">Select Construct</option>
      <option v-for="c in constructs" :key="c.id" :value="c.id">
        {{ c.name }}
      </option>
    </select>

    <!-- File Upload -->
    <input 
      type="file" 
      @change="handleFileSelect"
      accept=".xlsx,.xls,.csv"
      :disabled="!selectedConstructId"
    />

    <!-- Upload Button -->
    <button 
      @click="uploadFile" 
      :disabled="!file || !selectedConstructId || uploading"
    >
      {{ uploading ? 'Uploading...' : 'Upload Questions' }}
    </button>

    <!-- Results -->
    <div v-if="uploadResult">
      <p>✅ Success: {{ uploadResult.success_count }}</p>
      <p v-if="uploadResult.failure_count > 0">
        ❌ Failures: {{ uploadResult.failure_count }}
      </p>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      selectedConstructId: '',
      file: null,
      uploading: false,
      uploadResult: null
    }
  },
  methods: {
    handleFileSelect(event) {
      this.file = event.target.files[0];
    },
    async uploadFile() {
      if (!this.file || !this.selectedConstructId) return;

      const formData = new FormData();
      formData.append('file', this.file);
      formData.append('construct_id', this.selectedConstructId);

      this.uploading = true;
      try {
        const response = await axios.post('/api/questions/bulk-upload', formData, {
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'multipart/form-data'
          }
        });
        this.uploadResult = response.data.data;
        alert(`Uploaded ${response.data.data.success_count} questions successfully!`);
      } catch (error) {
        alert('Upload failed: ' + error.response.data.message);
      } finally {
        this.uploading = false;
      }
    }
  }
}
</script>
```

---

Happy Uploading! 🚀

