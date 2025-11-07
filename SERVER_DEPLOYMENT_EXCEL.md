# Server Deployment - Excel Upload Fix

## ⚠️ Issue

The server is returning a `500 Internal Server Error` with:
```
"Trait \"Maatwebsite\\Excel\\Concerns\\SkipsFailures\" not found"
```

This means the server has the **old version** of `maatwebsite/excel` (v1.1.5) installed, but the code requires **version 3.1.67+**.

---

## 🔧 Solution: Install Correct Package on Server

### Step 1: SSH into Your Server

```bash
ssh your-server
```

### Step 2: Navigate to Laravel Directory

```bash
cd /home/glansadesigns/public_html/api.glansadesigns.com/strengthscompass
```

### Step 3: Remove Old Package

```bash
composer remove maatwebsite/excel
```

### Step 4: Install Correct Version

```bash
composer require maatwebsite/excel:3.1.67 --ignore-platform-req=ext-gd --ignore-platform-req=ext-zip
```

**Note:** The `--ignore-platform-req` flags bypass missing PHP extensions. If your server has `gd` and `zip` extensions enabled, you can omit these flags.

### Step 5: Clear All Caches

```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Step 6: Verify Installation

```bash
composer show maatwebsite/excel
```

You should see: `versions : * 3.1.67`

---

## 🚀 Quick One-Liner

Or run everything at once:

```bash
cd /home/glansadesigns/public_html/api.glansadesigns.com/strengthscompass && \
composer remove maatwebsite/excel && \
composer require maatwebsite/excel:3.1.67 --ignore-platform-req=ext-gd --ignore-platform-req=ext-zip && \
php artisan route:clear && \
php artisan config:clear && \
php artisan cache:clear
```

---

## ✅ After Installation

Once installed, test the Excel upload again in Postman. It should work now!

---

## 🔍 Verify Package Version

To check what version is installed:

```bash
composer show maatwebsite/excel | grep versions
```

Should show: `versions : * 3.1.67`

---

## 📝 Files to Upload to Server

Make sure these files are on the server:

1. ✅ `app/Imports/QuestionsImport.php` - Already uploaded
2. ✅ `app/Http/Controllers/Api/QuestionsController.php` - Already uploaded  
3. ✅ `routes/api.php` - Already uploaded
4. ⚠️ **`composer.json`** - Update with new package requirement
5. ⚠️ **`composer.lock`** - Will be updated when you run composer require

---

## 🎯 Expected Result

After installation, the Excel upload endpoint should work without the `500` error.

Test with:
```
POST /api/questions/bulk-upload
```

---

## 💡 Alternative: Manual Package Installation

If composer commands fail, you can manually edit `composer.json`:

1. Edit `composer.json`
2. Find `"maatwebsite/excel"` in require section
3. Change to: `"maatwebsite/excel": "3.1.67"`
4. Run: `composer update maatwebsite/excel --ignore-platform-req=ext-gd --ignore-platform-req=ext-zip`

---

## ⚠️ Important Notes

1. **PHP Extensions**: For production, it's recommended to enable `gd` and `zip` extensions:
   ```bash
   # Check if extensions are enabled
   php -m | grep -E "gd|zip"
   ```

2. **Backup First**: Always backup before making changes:
   ```bash
   cp composer.json composer.json.backup
   cp composer.lock composer.lock.backup
   ```

3. **Test After**: Test the upload endpoint after installation to verify it works.

---

Once the package is installed on the server, the Excel upload will work! 🎉

