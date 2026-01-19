<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Snappy PDF / Image Configuration
    |--------------------------------------------------------------------------
    |
    | This option contains settings for PDF generation.
    |
    | Enabled:
    |    
    |    Whether to load PDF / Image generation.
    |
    | Binary:
    |    
    |    The file path of the wkhtmltopdf / wkhtmltoimage executable.
    |
    | Timeout:
    |    
    |    The amount of time to wait (in seconds) before PDF / Image generation is stopped.
    |    Setting this to false disables the timeout (unlimited processing time).
    |
    | Options:
    |
    |    The wkhtmltopdf command options. These are passed directly to wkhtmltopdf.
    |    See https://wkhtmltopdf.org/usage/wkhtmltopdf.txt for all options.
    |
    | Env:
    |
    |    The environment variables to set while running the wkhtmltopdf process.
    |
    */
    
    'pdf' => [
        'enabled' => true,
        // Set WKHTMLTOPDF_BINARY in .env file
        // Windows: WKHTMLTOPDF_BINARY="C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe"
        // Linux: WKHTMLTOPDF_BINARY="/usr/bin/wkhtmltopdf"
        'binary'  => env('WKHTMLTOPDF_BINARY'),
        'timeout' => false,
        'options' => [
            'enable-local-file-access' => true,
            'encoding' => 'UTF-8',
            'page-size' => 'A4',
            'orientation' => 'Portrait',
            'margin-top' => 15,
            'margin-bottom' => 20,
            'margin-left' => 15,
            'margin-right' => 15,
        ],
        'env'     => [
            // Set temp directory via WKHTMLTOPDF_TEMP environment variable
            // Windows: WKHTMLTOPDF_TEMP="C:/wkhtmltopdf-temp"
            // Linux: WKHTMLTOPDF_TEMP="/tmp"
            'TMPDIR' => env('WKHTMLTOPDF_TEMP', sys_get_temp_dir()),
            'TEMP' => env('WKHTMLTOPDF_TEMP', sys_get_temp_dir()),
            'TMP' => env('WKHTMLTOPDF_TEMP', sys_get_temp_dir()),
        ],
    ],
    
    'image' => [
        'enabled' => true,
        'binary'  => env('WKHTML_IMG_BINARY', base_path('vendor/h4cc/wkhtmltoimage-amd64/bin/wkhtmltoimage-amd64.exe')),
        'timeout' => false,
        'options' => [],
        'env'     => [],
    ],

];
