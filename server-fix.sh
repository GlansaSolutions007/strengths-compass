#!/bin/bash

# Server Fix Script for Constructs CRUD
# Run this on your server after uploading files

echo "🔧 Fixing Constructs CRUD Routes..."

# Navigate to Laravel directory (adjust path as needed)
cd /home/glansadesigns/public_html/api.glansadesigns.com/strengthscompass

echo "📦 Running migration..."
php artisan migrate --force

echo "🧹 Clearing all caches..."
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo "✅ Verifying routes..."
php artisan route:list --path=constructs

echo "🎉 Done! Test your endpoints now."

