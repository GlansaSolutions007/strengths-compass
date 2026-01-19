# Snappy PDF Setup - Configuration Complete ✅

## What's Been Done

1. ✅ **Laravel Snappy Package**: Already installed (`barryvdh/laravel-snappy`)
2. ✅ **wkhtmltopdf Binary**: Installed via Composer (`h4cc/wkhtmltopdf-amd64`)
3. ✅ **Config File**: Created at `config/snappy.php`
4. ✅ **Controller Method**: `downloadSnappyPdf()` in `ReportController`
5. ✅ **Blade Template**: `resources/views/reports/snappy-report.blade.php`
6. ✅ **API Route**: `GET /api/test-results/{testResultId}/report/pdf/snappy`

## Configuration Details

### Binary Path
The wkhtmltopdf binary is located at:
```
vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64.exe
```

This is automatically configured in `config/snappy.php`.

### API Endpoint
```
GET /api/test-results/{testResultId}/report/pdf/snappy?age_group_id=4
```

### Example Usage

**cURL:**
```bash
curl -X GET "http://127.0.0.1:8000/api/test-results/26/report/pdf/snappy?age_group_id=4" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output report.pdf
```

**JavaScript/Fetch:**
```javascript
fetch('http://127.0.0.1:8000/api/test-results/26/report/pdf/snappy?age_group_id=4', {
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  }
})
.then(response => response.blob())
.then(blob => {
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'report.pdf';
  a.click();
});
```

## Testing

### Test the Endpoint
1. Make sure your Laravel server is running
2. Call the API endpoint with a valid test result ID
3. The PDF should download automatically

### Verify Binary
```bash
php artisan tinker
>>> $snappy = app('snappy.pdf');
>>> $snappy->getBinary();
```

Should return the path to the binary.

## Next Steps

### 1. Generate Radar Charts
The radar charts need to be generated. You have two options:

**Option A: Client-side (Recommended)**
- Generate charts using Chart.js/D3.js in your frontend
- Convert to base64 and pass to the API
- Update the controller to accept base64 charts

**Option B: Server-side**
- Implement `generateRadarChartBase64()` method
- Use Puppeteer or headless Chrome to render charts
- Or use a chart generation service

### 2. Add Logo
Update the cover page in `snappy-report.blade.php`:
```blade
<img src="{{ asset('images/logo.png') }}" class="cover-logo" alt="Logo" />
```

### 3. Customize Styling
Edit `resources/views/reports/snappy-report.blade.php` to match your brand colors.

## Troubleshooting

### Error: "Binary not found"
- Check if binary exists: `vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64.exe`
- Update path in `config/snappy.php` or `.env`:
  ```
  WKHTML_PDF_BINARY="C:\xampp\htdocs\glansa1\strengths-compass\vendor\h4cc\wkhtmltopdf-amd64\bin\wkhtmltopdf-amd64.exe"
  ```

### Error: "Permission denied"
- On Linux/Mac, make binary executable:
  ```bash
  chmod +x vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64
  ```

### Charts not showing
- Implement chart generation (see Next Steps #1)
- Or pass base64 charts from frontend

### Page breaks not working
- Ensure CSS `page-break-before: always` is used
- Check wkhtmltopdf version (0.12.4+ is installed)

## Files Created/Modified

- ✅ `config/snappy.php` - Snappy configuration
- ✅ `app/Http/Controllers/Api/ReportController.php` - Added `downloadSnappyPdf()` method
- ✅ `resources/views/reports/snappy-report.blade.php` - PDF template
- ✅ `routes/api.php` - Added route
- ✅ `composer.json` - Added `h4cc/wkhtmltopdf-amd64` dependency

## Ready to Use! 🎉

Your Snappy PDF generation is now configured and ready to use. Test the endpoint with a valid test result ID to generate your first PDF!
