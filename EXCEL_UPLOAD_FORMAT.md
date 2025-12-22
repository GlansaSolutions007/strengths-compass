# Excel Upload Format for School and Organization Users

## 📋 Overview

This document describes the Excel file format required for bulk importing users for schools and organizations.

---

## 🏫 School Users Excel Format

### Endpoint
```
POST /api/users/import-school-users
Content-Type: multipart/form-data

Parameters:
- school_id: (required) ID of the school
- file: (required) Excel file (.xlsx, .xls, .csv)
```

### Excel Column Headers (First Row)

| Column Name | Required | Type | Description | Example |
|------------|----------|------|-------------|---------|
| `first_name` | ✅ **Required** | Text | Student's first name | "John" |
| `last_name` | ✅ **Required** | Text | Student's last name | "Doe" |
| `class` | ✅ **Required** | Text | Class/Standard (e.g., "10th", "12th", "Class 5") | "10th" |
| `registration_no` | ✅ **Required** | Text | Student registration number | "12345" |
| `password` | ✅ **Required** | Text | Login password (min 6 characters) | "student123" |
| `age` | ✅ **Required** | Number | Student age (1-150) | 15 |
| `gender` | Optional | Text | Gender: `male`, `female`, `other`, `prefer_not_to_say` | "male" |
| `contact_number` | Optional | Text | Contact number (max 20 chars) | "+1234567890" |
| `whatsapp_number` | Optional | Text | WhatsApp number (max 20 chars) | "+1234567890" |
| `city` | Optional | Text | City name | "New York" |
| `state` | Optional | Text | State name | "NY" |
| `country` | Optional | Text | Country name | "USA" |
| `educational_qualification` | Optional | Text | Educational qualification | "10th Grade" |

### Username Generation
**Format:** `shortcode + class + registration_no` (all lowercase)

**Example:**
- School shortcode: "ABC"
- Class: "10th"
- Registration No: "12345"
- **Generated Username:** `abc10th12345`

### Sample Excel Data (School Users)

| first_name | last_name | class | registration_no | password | age | gender | city | state |
|------------|-----------|-------|-----------------|----------|-----|--------|------|-------|
| John | Doe | 10th | 12345 | pass123 | 15 | male | New York | NY |
| Jane | Smith | 12th | 12346 | pass456 | 17 | female | New York | NY |
| Bob | Johnson | 9th | 12347 | pass789 | 14 | male | Boston | MA |

---

## 🏢 Organization Users Excel Format

### Endpoint
```
POST /api/users/import-organization-users
Content-Type: multipart/form-data

Parameters:
- organization_id: (required) ID of the organization
- file: (required) Excel file (.xlsx, .xls, .csv)
```

### Excel Column Headers (First Row)

| Column Name | Required | Type | Description | Example |
|------------|----------|------|-------------|---------|
| `first_name` | ✅ **Required** | Text | Employee's first name | "John" |
| `last_name` | ✅ **Required** | Text | Employee's last name | "Doe" |
| `employee_id` | ✅ **Required** | Text | Employee ID | "EMP001" |
| `password` | ✅ **Required** | Text | Login password (min 6 characters) | "emp123456" |
| `age` | ✅ **Required** | Number | Employee age (1-150) | 30 |
| `gender` | Optional | Text | Gender: `male`, `female`, `other`, `prefer_not_to_say` | "male" |
| `contact_number` | Optional | Text | Contact number (max 20 chars) | "+1234567890" |
| `whatsapp_number` | Optional | Text | WhatsApp number (max 20 chars) | "+1234567890" |
| `city` | Optional | Text | City name | "Los Angeles" |
| `state` | Optional | Text | State name | "CA" |
| `country` | Optional | Text | Country name | "USA" |
| `profession` | Optional | Text | Job title/Profession | "Software Engineer" |
| `educational_qualification` | Optional | Text | Educational qualification | "Bachelor's Degree" |

### Username Generation
**Format:** `shortcode + employee_id` (all lowercase)

**Example:**
- Organization shortcode: "XYZ"
- Employee ID: "EMP001"
- **Generated Username:** `xyzemp001`

### Sample Excel Data (Organization Users)

| first_name | last_name | employee_id | password | age | gender | profession | city | state |
|------------|-----------|-------------|----------|-----|--------|------------|------|-------|
| John | Doe | EMP001 | emp123 | 30 | male | Software Engineer | LA | CA |
| Jane | Smith | EMP002 | emp456 | 28 | female | HR Manager | LA | CA |
| Bob | Johnson | EMP003 | emp789 | 35 | male | Sales Executive | SF | CA |

---

## ⚠️ Important Notes

### Prerequisites
1. **School/Organization must exist** - Create the school/organization first via CRUD endpoints
2. **Shortcode must be set** - The school/organization must have a `shortcode` set before importing users
3. **Unique usernames** - The system will check if the generated username already exists

### Validation Rules
- **Password:** Minimum 6 characters
- **Age:** Must be between 1 and 150
- **Gender:** Must be one of: `male`, `female`, `other`, `prefer_not_to_say` (defaults to `prefer_not_to_say` if not provided)
- **Email:** Not required - username is used as email for login

### Error Handling
- If a row fails validation, it will be skipped and reported in the response
- The import will continue processing other rows
- Response includes:
  - `success_count`: Number of users successfully imported
  - `failure_count`: Number of rows that failed
  - `errors`: Array of errors with row data and error messages

### File Format
- Supported formats: `.xlsx`, `.xls`, `.csv`
- Maximum file size: 10MB
- First row must contain column headers (exact match, case-sensitive)
- Column headers should match exactly as shown above

---

## 📝 Example API Request

### School Users Import
```bash
curl -X POST "https://your-api.com/api/users/import-school-users" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "school_id=1" \
  -F "file=@school_users.xlsx"
```

### Organization Users Import
```bash
curl -X POST "https://your-api.com/api/users/import-organization-users" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "organization_id=1" \
  -F "file=@organization_users.xlsx"
```

---

## ✅ Success Response Example

```json
{
  "status": 200,
  "message": "Import completed. 50 users imported successfully, 2 failed",
  "data": {
    "success_count": 50,
    "failure_count": 2,
    "errors": [
      {
        "row": {
          "first_name": "Test",
          "last_name": "User",
          "class": "",
          "registration_no": "12345"
        },
        "error": "Class and Registration No are required for school users"
      }
    ]
  }
}
```

---

## 🔑 Login Credentials

After import, users can login using:
- **Username:** Generated username (shortcode + class + registration_no for schools, shortcode + employee_id for organizations)
- **Password:** The password provided in the Excel file

**Note:** The username is also stored as the email field in the database for authentication purposes.

