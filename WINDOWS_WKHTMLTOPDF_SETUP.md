# Windows wkhtmltopdf Setup

## Quick Setup for Windows

### Step 1: Download wkhtmltopdf for Windows

1. Visit: https://wkhtmltopdf.org/downloads.html
2. Download the **Windows (MSVC)** installer (64-bit recommended)
3. Install it to the default location: `C:\Program Files\wkhtmltopdf\`

### Step 2: Configure in .env

Add to your `.env` file:

```env
WKHTML_PDF_BINARY="C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe"
```

**IMPORTANT:** The path MUST be wrapped in double quotes because it contains spaces. The quotes are part of the path value, not just for .env syntax.

### Step 3: Verify Installation

Test if wkhtmltopdf is accessible:

```bash
"C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe" --version
```

### Step 4: Clear Config Cache

```bash
php artisan config:clear
```

## Alternative: Custom Installation Path

If you installed wkhtmltopdf to a different location, update `.env`:

```env
WKHTML_PDF_BINARY="D:\Tools\wkhtmltopdf\bin\wkhtmltopdf.exe"
```

## Testing

After setup, test the PDF generation:

```bash
# Test via API
curl -X GET "http://127.0.0.1:8000/api/test-results/26/report/pdf/snappy?age_group_id=4"
```

## Troubleshooting

### Error: "'C:\\Program' is not recognized as an internal or external command"
**This means the path with spaces is not properly quoted.**

**Solution 1: Add quotes in .env (Recommended)**
```env
WKHTML_PDF_BINARY="C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe"
```
The quotes are part of the path value itself, not just .env syntax.

**Solution 2: Use short path name (8.3 format)**
```bash
# Get short path name
dir /x "C:\Program Files"
# Use the short name like: C:\PROGRA~1\wkhtmltopdf\bin\wkhtmltopdf.exe
```

**Solution 3: Install to path without spaces**
Install wkhtmltopdf to `C:\wkhtmltopdf\` instead of `C:\Program Files\`
Then use:
```env
WKHTML_PDF_BINARY=C:\wkhtmltopdf\bin\wkhtmltopdf.exe
```

### Error: "The system cannot find the file specified"
- Verify the path in `.env` is correct
- Use double quotes in `.env` if path has spaces
- Check if the file exists at that path
- Try using forward slashes: `C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe`

### Error: "Permission denied"
- Run your Laravel application with appropriate permissions
- Check if the binary file has execute permissions

### Binary Not Found
- Download and install wkhtmltopdf from the official website
- Update the path in `.env` file (with quotes if path has spaces)
- Clear config cache: `php artisan config:clear`
