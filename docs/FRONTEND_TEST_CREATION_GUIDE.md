# Frontend Guide: Creating and Managing Tests

This guide describes how to implement **test creation and management** in the frontend using the Strengths Compass API. It covers SC Pro tests, CERC tests, Excel upload, and optional manual question selection.

---

## Table of contents

1. [API base and auth](#1-api-base-and-auth)
2. [List and filter tests](#2-list-and-filter-tests)
3. [Create a test (SC Pro or CERC)](#3-create-a-test-sc-pro-or-cerc)
4. [Download Excel template](#4-download-excel-template)
5. [Upload Excel (create or update)](#5-upload-excel-create-or-update)
6. [Standalone import (add questions to existing test)](#6-standalone-import-add-questions-to-existing-test)
7. [Update a test](#7-update-a-test)
8. [Get test detail, questions, constructs](#8-get-test-detail-questions-constructs)
9. [Clusters: attach, detach, category counts](#9-clusters-attach-detach-category-counts)
10. [CERC: link to SC Pro](#10-cerc-link-to-sc-pro)
11. [Example flows (step-by-step)](#11-example-flows-step-by-step)
12. [Excel format reference](#12-excel-format-reference)
13. [Error handling](#13-error-handling)

---

## 1. API base and auth

- **Base URL:** `https://your-api-domain.com/api` (or your configured API URL).
- **Auth:** Use the same auth your app uses (e.g. Bearer token, session). All endpoints below assume the request is authenticated as required by your routes.

---

## 2. List and filter tests

**Endpoint:** `GET /api/tests`

**Query parameters (all optional):**

| Parameter      | Type    | Description                    |
|----------------|---------|--------------------------------|
| `age_group_id` | integer | Filter by age group.           |
| `is_active`    | boolean | Filter by active/inactive.     |
| `source`       | string  | `"SC Pro"` or `"CERC"`.        |

**Example:**

```http
GET /api/tests?age_group_id=1&source=SC%20Pro&is_active=1
```

**Response (200):**

```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "title": "SC Pro Adults",
      "description": "...",
      "age_group_id": 1,
      "is_active": true,
      "source": "SC Pro",
      "sc_pro_test_id": null,
      "clusters": [...],
      "age_group": { "id": 1, "name": "Adults" }
    }
  ],
  "message": "Tests fetched successfully"
}
```

Use this to show a tests list and to populate the **SC Pro test** dropdown when creating a CERC test.

---

## 3. Create a test (SC Pro or CERC)

**Endpoint:** `POST /api/tests`

**Content-Type:**  
- JSON only: `Content-Type: application/json`  
- With Excel: `Content-Type: multipart/form-data`

### 3.1 SC Pro test (JSON, no file)

**Body (JSON):**

```json
{
  "title": "SC Pro Adults",
  "description": "Optional description",
  "age_group_id": 1,
  "is_active": true,
  "source": "SC Pro",
  "cluster_ids": [1, 2, 3],
  "clusters": [
    { "cluster_id": 1, "p_count": 2, "r_count": 2, "sdb_count": 1 },
    { "cluster_id": 2, "p_count": 2, "r_count": 2, "sdb_count": 1 }
  ]
}
```

- `cluster_ids`: optional array of cluster IDs (no category counts).  
- `clusters`: optional array of `{ cluster_id, p_count?, r_count?, sdb_count? }` for category counts.  
- Omit both if you will add questions only via Excel.

### 3.2 SC Pro test (with Excel in one request)

**Body (multipart/form-data):**

| Field            | Type   | Required | Description |
|------------------|--------|----------|-------------|
| `title`          | string | Yes      | Test title. |
| `description`    | string | No       | Description. |
| `age_group_id`   | number | No       | Age group ID. |
| `is_active`      | boolean| No       | Default true. |
| `source`         | string | No       | `"SC Pro"` (default). |
| `questions_file` | file   | No       | Excel (.xlsx, .xls, .csv, max 10MB). |
| `cluster_ids`    | array  | No       | Optional cluster IDs. |
| `clusters`       | array  | No       | Optional `[{ cluster_id, p_count?, r_count?, sdb_count? }]`. |
| `question_ids`   | array  | No       | Optional manual question IDs (if no file). |

If both `questions_file` and `question_ids` are sent, `question_ids` are attached first, then the file is imported and all imported rows are attached (existing test questions are not cleared on create; they are appended).

**Example (FormData in JavaScript):**

```javascript
const formData = new FormData();
formData.append('title', 'SC Pro Adults');
formData.append('description', 'Assessment for adults');
formData.append('age_group_id', 1);
formData.append('source', 'SC Pro');
formData.append('questions_file', fileInput.files[0]); // File input

const response = await fetch('/api/tests', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
  },
  body: formData,
});
```

**Response (201):**

```json
{
  "status": true,
  "message": "Test created successfully with 54 questions imported.",
  "data": {
    "id": 1,
    "title": "SC Pro Adults",
    "description": "...",
    "age_group_id": 1,
    "is_active": true,
    "source": "SC Pro",
    "sc_pro_test_id": null,
    "clusters": [...],
    "selected_questions_count": 54
  },
  "import_stats": {
    "success": 54,
    "failure_count": 0,
    "questions_created": 54
  }
}
```

If there are import errors, the response may include `import_errors` and a different `message`.

### 3.3 CERC test

**Required:** `source: "CERC"` and `sc_pro_test_id: <id of SC Pro test>`.

**Body (multipart recommended if using Excel):**

| Field            | Type   | Required | Description |
|------------------|--------|----------|-------------|
| `title`          | string | Yes      | Test title. |
| `description`    | string | No       | Description. |
| `age_group_id`   | number | No       | Age group ID. |
| `is_active`      | boolean| No       | Default true. |
| `source`         | string | Yes      | `"CERC"`. |
| `sc_pro_test_id` | number | Yes      | ID of the SC Pro test to link. |
| `questions_file` | file   | No       | Excel (can reuse questions/constructs; see SC Pro / CERC import doc). |
| `cluster_ids` / `clusters` | - | No | Optional. |

**Example (FormData):**

```javascript
formData.append('title', 'CERC Adults');
formData.append('age_group_id', 1);
formData.append('source', 'CERC');
formData.append('sc_pro_test_id', scProTestId); // e.g. 1
formData.append('questions_file', fileInput.files[0]);
```

---

## 4. Download Excel template

**Endpoint:** `GET /api/tests/questions/template`

**Query:**

| Parameter      | Type    | Description |
|----------------|---------|-------------|
| `age_group_id` | integer | Optional. Prefills constructs for that age group. |

**Example:**

```http
GET /api/tests/questions/template?age_group_id=1
```

**Response:** Binary Excel file (attachment).  
Suggested filename from backend: `test-questions-template-age-group-1-YYYY-MM-DD_HIS.xlsx`.

**Frontend (download link or button):**

```javascript
// Option A: open in new tab (uses auth if cookie-based)
window.open(`/api/tests/questions/template?age_group_id=${ageGroupId}`, '_blank');

// Option B: fetch and save as file (Bearer auth)
const res = await fetch(`/api/tests/questions/template?age_group_id=${ageGroupId}`, {
  headers: { 'Authorization': `Bearer ${token}` },
});
const blob = await res.blob();
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = 'test-questions-template.xlsx';
a.click();
URL.revokeObjectURL(url);
```

---

## 5. Upload Excel (create or update)

- **Create:** Use `POST /api/tests` with `questions_file` in the body (see section 3).
- **Update:** Use `PUT /api/tests/{id}` with `questions_file`.  
  **Important:** On update, when a file is sent, existing questions for that test are **replaced** by the result of the import (all rows in the file are attached; previous test_question rows for that test are removed first).

**Excel columns:** See [Section 12](#12-excel-format-reference).

---

## 6. Standalone import (add questions to existing test)

Use this when the test already exists and you only want to **add** questions from an Excel file (without replacing other test fields). Questions from the file are **appended**; existing test questions are **not** deleted.

**Endpoint:** `POST /api/tests/{id}/questions/import`

**Content-Type:** `multipart/form-data`

**Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | file | Yes | Excel (.xlsx, .xls, .csv, max 10MB). |

**Example:**

```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);

const response = await fetch(`/api/tests/${testId}/questions/import`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
  },
  body: formData,
});
```

**Response (200):**

```json
{
  "status": true,
  "message": "Questions imported successfully",
  "data": {
    "test_id": 2,
    "success_count": 20,
    "failure_count": 0,
    "created_count": 5,
    "reused_count": 15,
    "questions_attached": 20,
    "selected_questions_count": 74
  }
}
```

If there are validation/import errors, the response may include `errors` and a different `message`.

---

## 7. Update a test

**Endpoint:** `PUT /api/tests/{id}`

**Content-Type:**  
- JSON: `application/json`  
- With file: `multipart/form-data`

**Body (same field names as create):**

- `title`, `description`, `age_group_id`, `is_active`, `source`, `sc_pro_test_id`
- `clusters`: array of `{ cluster_id, p_count?, r_count?, sdb_count? }` — **replaces** test clusters.
- `questions_file`: if sent, **replaces** all questions for this test with the import result.
- `question_ids`: used only if **no** `questions_file` is sent; **replaces** selected questions with the given IDs.

So: either send a file (replace questions by import) or send `question_ids` (replace by selection), not both for “replace” semantics.

**Response (200):**

```json
{
  "status": true,
  "message": "Test updated successfully",
  "data": { ...test with relations... },
  "selected_questions_count": 54
}
```

---

## 8. Get test detail, questions, constructs

| Action | Endpoint | Response |
|--------|----------|----------|
| Single test with clusters and selected questions | `GET /api/tests/{id}` | `data`: test + `selected_questions`, `selected_questions_count`. |
| Selected questions only | `GET /api/tests/{id}/questions` | `data`: array of questions, `count`. |
| Constructs for this test | `GET /api/tests/{id}/constructs` | `data`: array of constructs (test-specific). |

Use these to show test details, question list, and construct list (e.g. for dropdowns or review).

---

## 9. Clusters: attach, detach, category counts

- **Attach clusters:**  
  `POST /api/tests/{id}/clusters/attach`  
  Body (JSON): `{ "cluster_ids": [1, 2, 3] }`

- **Detach clusters:**  
  `POST /api/tests/{id}/clusters/detach`  
  Body (JSON): `{ "cluster_ids": [2] }`

- **Set category counts for a cluster:**  
  `PUT /api/tests/{testId}/clusters/{clusterId}/category-counts`  
  Body (JSON): `{ "p_count": 2, "r_count": 2, "sdb_count": 1 }`

- **Generate question selection (auto-pick by counts):**  
  `POST /api/tests/{id}/generate-questions`  
  Uses existing cluster category counts to select questions. No body required.

Use these if you manage clusters and auto-selection without Excel.

---

## 10. CERC: link to SC Pro

- When **creating** a CERC test, set `sc_pro_test_id` to the chosen SC Pro test ID (from `GET /api/tests?source=SC%20Pro` or similar).

- **List CERC tests for an SC Pro test:**  
  `GET /api/tests/{scProTestId}/cerc-tests`  
  Returns `sc_pro_test` and `cerc_tests` array. Use this to show “CERC tests linked to this SC Pro” in the UI.

---

## 11. Example flows (step-by-step)

### Flow A: Create SC Pro test with Excel only

1. **Optional:** Fetch age groups: `GET /api/age-groups`.
2. **Optional:** Download template: `GET /api/tests/questions/template?age_group_id=1`. User fills Cluster, Construct, Question, Category.
3. Create test with file:  
   `POST /api/tests`  
   FormData: `title`, `description`, `age_group_id`, `source` = `"SC Pro"`, `questions_file`.
4. Show success and `data.id` (test id). Optionally redirect to test detail: `GET /api/tests/{id}`.

### Flow B: Create CERC test with Excel

1. List SC Pro tests: `GET /api/tests?source=SC%20Pro&is_active=1`.
2. User selects an SC Pro test (e.g. id = 1).
3. Download template (optional): `GET /api/tests/questions/template?age_group_id=1`. User fills Excel (can reuse constructs; optional column **Question ID** to reuse questions).
4. Create CERC test:  
   `POST /api/tests`  
   FormData: `title`, `age_group_id`, `source` = `"CERC"`, `sc_pro_test_id` = `1`, `questions_file`.
5. Show success and optionally `import_stats` (created_count, reused_count, etc.).

### Flow C: Create test first, add questions later

1. Create test (no file):  
   `POST /api/tests`  
   JSON: `{ "title": "...", "age_group_id": 1, "source": "SC Pro" }`.
2. User downloads template, fills it, then:  
   `POST /api/tests/{id}/questions/import`  
   FormData: `file`.  
   Or replace questions by file on update:  
   `PUT /api/tests/{id}`  
   FormData: `questions_file` (and any other fields to update).

### Flow D: Edit test and replace questions by Excel

1. Load test: `GET /api/tests/{id}`.
2. User uploads new Excel.  
   `PUT /api/tests/{id}`  
   FormData: `questions_file` (and optionally `title`, `description`, etc.).  
   Backend replaces all questions for this test with the import result.

---

## 12. Excel format reference

| Column       | Required | Description |
|-------------|----------|-------------|
| **Cluster** | Yes      | Cluster name for this test. |
| **Construct** | Yes    | Construct name (reused if exists, else created). |
| **Question** | Yes     | Question text (reused if same text + construct, else created). |
| **Category** | Yes     | `P`, `R`, or `SDB`. |
| **Question ID** | No   | If provided and exists, that question is reused (no new question created). Useful for CERC. |
| **Source**  | No       | `SC Pro` or `CERC`. |

File types: `.xlsx`, `.xls`, `.csv`. Max size: 10 MB.

---

## 13. Error handling

- **422 Validation failed:** Response includes `errors` (field-wise messages). Show them next to the form (e.g. under each field or in an alert).
- **404 Test not found:** Show “Test not found” and optionally redirect to tests list.
- **500 Server error:** Response may include `message`. Show a generic error and optionally log or report.

**Example (create with file):**

```javascript
const res = await fetch('/api/tests', { method: 'POST', body: formData, ... });
const json = await res.json();

if (!json.status) {
  if (res.status === 422 && json.errors) {
    // Show json.errors per field
    return;
  }
  // Show json.message
  return;
}

// Success: json.data, optional json.import_stats, json.import_errors
```

---

## Quick reference: test endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/tests` | List tests (optional filters). |
| GET | `/api/tests/{id}` | Get test with questions. |
| POST | `/api/tests` | Create test (JSON or FormData + file). |
| PUT | `/api/tests/{id}` | Update test (optional file replaces questions). |
| DELETE | `/api/tests/{id}` | Delete test. |
| GET | `/api/tests/questions/template` | Download Excel template. |
| POST | `/api/tests/{id}/questions/import` | Import Excel (append questions). |
| GET | `/api/tests/{id}/questions` | Get selected questions. |
| GET | `/api/tests/{id}/constructs` | Get constructs for test. |
| GET | `/api/tests/{id}/cerc-tests` | Get CERC tests for SC Pro test. |
| POST | `/api/tests/{id}/clusters/attach` | Attach clusters. |
| POST | `/api/tests/{id}/clusters/detach` | Detach clusters. |
| PUT | `/api/tests/{testId}/clusters/{clusterId}/category-counts` | Set P/R/SDB counts. |
| POST | `/api/tests/{id}/generate-questions` | Auto-generate question selection. |

Use this guide together with [SC_PRO_CERC_IMPORT.md](SC_PRO_CERC_IMPORT.md) for import rules (no duplicate questions/constructs, CERC reuse, and cluster–construct per test).
