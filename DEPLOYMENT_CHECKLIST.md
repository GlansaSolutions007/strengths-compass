# Deployment Checklist - Constructs CRUD

## ⚠️ IMPORTANT: Server Deployment Steps

The routes are correctly configured locally but need to be deployed to your server.

### Step 1: Upload Files to Server

Upload these files to your server:

1. **routes/api.php** - Updated with construct routes
2. **app/Http/Controllers/Api/ConstructController.php** - New controller file
3. **app/Models/Construct.php** - Updated model with fillable fields
4. **database/migrations/2025_11_06_045116_add_fields_to_constructs_table.php** - New migration

### Step 2: Run Migration on Server

SSH into your server and run:

```bash
cd /home/glansadesigns/public_html/api.glansadesigns.com/strengthscompass
php artisan migrate
```

### Step 3: Clear All Caches on Server

```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Step 4: Verify Routes on Server

```bash
php artisan route:list --path=constructs
```

You should see all 5 construct routes listed.

---

## 🔍 Troubleshooting

### If routes still don't work:

1. **Check file permissions:**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

2. **Check if ConstructController exists:**
   ```bash
   ls -la app/Http/Controllers/Api/ConstructController.php
   ```

3. **Verify routes file is correct:**
   ```bash
   cat routes/api.php | grep constructs
   ```

4. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 📝 Quick Test After Deployment

Once deployed, test with:

```bash
curl -X GET "https://api.glansadesigns.com/strengthscompass/api/constructs" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

If this works, your POST request should also work.

