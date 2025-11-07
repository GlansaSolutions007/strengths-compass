#!/bin/bash

# Server Fix Script for Excel Upload
# Run this on your server to install the correct maatwebsite/excel version

echo "🔧 Fixing Excel Upload on Server..."

# Navigate to Laravel directory
cd /home/glansadesigns/public_html/api.glansadesigns.com/strengthscompass

echo "📦 Removing old maatwebsite/excel package..."
composer remove maatwebsite/excel

echo "📦 Installing correct maatwebsite/excel version (3.1.67)..."
composer require maatwebsite/excel:3.1.67 --ignore-platform-req=ext-gd --ignore-platform-req=ext-zip

echo "🧹 Clearing all caches..."
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo "✅ Done! Excel upload should work now."

