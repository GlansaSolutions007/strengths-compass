# Snappy PDF Report Setup Guide

## Installation

### 1. Install Laravel Snappy Package

```bash
composer require barryvdh/laravel-snappy
```

### 2. Install wkhtmltopdf Binary

#### Windows:
Download from: https://wkhtmltopdf.org/downloads.html
- Install the Windows installer
- Add to PATH or configure path in `config/snappy.php`

#### Linux (Ubuntu/Debian):
```bash
sudo apt-get install wkhtmltopdf
```

#### macOS:
```bash
brew install wkhtmltopdf
```

### 3. Publish Snappy Config

```bash
php artisan vendor:publish --provider="Barryvdh\Snappy\ServiceProvider"
```

### 4. Configure Snappy (config/snappy.php)

```php
'pdf' => [
    'enabled' => true,
    'binary' => env('WKHTML_PDF_BINARY', '/usr/local/bin/wkhtmltopdf'), // Update path
    'timeout' => false,
    'options' => [
        'page-size' => 'A4',
        'encoding' => 'UTF-8',
    ],
],
```

### 5. Set Environment Variable (Optional)

Add to `.env`:
```
WKHTML_PDF_BINARY=/path/to/wkhtmltopdf
```

## Usage

### API Endpoint

```
GET /api/test-results/{testResultId}/report/pdf/snappy?age_group_id=4
```

### Example Request

```bash
curl -X GET "http://127.0.0.1:8000/api/test-results/26/report/pdf/snappy?age_group_id=4" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output report.pdf
```

### Response

Returns PDF file with proper headers for download.

## PDF Structure

1. **Page 1**: Cover page with logo, title, user name, test name, date
2. **Pages 2-10**: One cluster per page with:
   - Cluster name and band (Low/Medium/High)
   - Description
   - Tendency/Behavior
   - Constructs with their bands and behaviors
3. **Page 11**: Cluster Radar Chart
4. **Page 12**: Construct Radar Chart
5. **Last Page**: Disclaimer with contact email

## Chart Generation

The radar charts need to be generated separately. Options:

1. **Client-side generation**: Generate charts using Chart.js/D3.js and pass as base64
2. **Server-side**: Use Puppeteer or headless Chrome to render charts
3. **Chart service**: Use a chart generation API
4. **Pre-generate**: Generate charts and store as images

Currently, `generateRadarChartBase64()` returns `null`. You need to implement chart generation based on your preferred method.

## Troubleshooting

### Error: "Snappy PDF library not available"
- Ensure `barryvdh/laravel-snappy` is installed
- Run `composer dump-autoload`

### Error: "wkhtmltopdf not found"
- Verify wkhtmltopdf is installed
- Check binary path in `config/snappy.php`
- Set `WKHTML_PDF_BINARY` in `.env`

### Charts not showing
- Implement `generateRadarChartBase64()` method
- Or pass base64 charts from frontend via API

### Page breaks not working
- Ensure CSS `page-break-before: always` is used
- Check wkhtmltopdf version (0.12.6+ recommended)

## Template Location

- Blade Template: `resources/views/reports/snappy-report.blade.php`
- Controller Method: `app/Http/Controllers/Api/ReportController.php::downloadSnappyPdf()`
