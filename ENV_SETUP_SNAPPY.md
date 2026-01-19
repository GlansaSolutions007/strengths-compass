# Snappy PDF Environment Variables Setup

## .env Configuration

Add these variables to your `.env` file based on your operating system:

### Windows (.env)
```env
WKHTMLTOPDF_BINARY="C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe"
WKHTMLTOPDF_TEMP="C:/wkhtmltopdf-temp"
```

**Important:** 
- Use forward slashes (`/`) instead of backslashes
- Wrap paths with spaces in double quotes
- The quotes are part of the value, not just .env syntax

### Linux/Production Server (.env)
```env
WKHTMLTOPDF_BINARY="/usr/bin/wkhtmltopdf"
WKHTMLTOPDF_TEMP="/tmp"
```

## Configuration Details

The `config/snappy.php` file is configured to read from these environment variables:

```php
'binary' => env('WKHTMLTOPDF_BINARY'),
'env' => [
    'TMPDIR' => env('WKHTMLTOPDF_TEMP', sys_get_temp_dir()),
    'TEMP' => env('WKHTMLTOPDF_TEMP', sys_get_temp_dir()),
    'TMP' => env('WKHTMLTOPDF_TEMP', sys_get_temp_dir()),
],
```

## Setup Steps

1. **Add to .env file** (choose based on your OS):
   - Windows: Use the Windows format above
   - Linux: Use the Linux format above

2. **Create temp directory** (Windows only):
   ```bash
   # The directory C:/wkhtmltopdf-temp should exist
   # It will be created automatically if it doesn't exist
   ```

3. **Clear config cache**:
   ```bash
   php artisan config:clear
   ```

4. **Test the endpoint**:
   ```
   GET /api/test-results/{testResultId}/report/pdf/snappy?age_group_id=4
   ```

## Verification

To verify your configuration:

```bash
php artisan tinker
>>> config('snappy.pdf.binary')
>>> config('snappy.pdf.env')
```

Should show your configured binary path and temp directory.
