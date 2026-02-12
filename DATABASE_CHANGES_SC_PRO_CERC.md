# Database Changes for SC Pro and CERC Feature

## Overview

This document lists all database migrations required for the SC Pro and CERC test feature implementation.

---

## Migration Files

### 1. Add Source Field to Tables
**File:** `database/migrations/2026_02_11_060102_add_source_to_questions_clusters_constructs_tests_tables.php`

**Changes:**
- Adds `source` enum field to `clusters` table
- Adds `source` enum field to `constructs` table
- Adds `source` enum field to `questions` table
- Adds `source` enum field to `tests` table

**Details:**
- **Field Type:** `enum('SC Pro', 'CERC')`
- **Default Value:** `'SC Pro'` (for backward compatibility)
- **Nullable:** No (has default value)

**SQL Equivalent:**
```sql
ALTER TABLE `clusters` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' AFTER `description`;

ALTER TABLE `constructs` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' AFTER `description`;

ALTER TABLE `questions` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' AFTER `question_text`;

ALTER TABLE `tests` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' AFTER `description`;
```

---

### 2. Add SC Pro Test ID to Tests Table
**File:** `database/migrations/2026_02_11_063248_add_sc_pro_test_id_to_tests_table.php`

**Changes:**
- Adds `sc_pro_test_id` foreign key to `tests` table

**Details:**
- **Field Type:** `foreignId` (bigInteger unsigned)
- **Nullable:** Yes
- **Foreign Key:** References `tests.id`
- **On Delete:** `SET NULL`
- **Position:** After `source` column
- **Purpose:** Links CERC tests to their corresponding SC Pro test

**SQL Equivalent:**
```sql
ALTER TABLE `tests` 
ADD COLUMN `sc_pro_test_id` BIGINT UNSIGNED NULL AFTER `source`,
ADD CONSTRAINT `tests_sc_pro_test_id_foreign` 
    FOREIGN KEY (`sc_pro_test_id`) 
    REFERENCES `tests` (`id`) 
    ON DELETE SET NULL;
```

---

## Complete Database Schema Changes

### Tables Modified

#### 1. `clusters` Table
```sql
ALTER TABLE `clusters` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' NOT NULL AFTER `description`;
```

#### 2. `constructs` Table
```sql
ALTER TABLE `constructs` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' NOT NULL AFTER `description`;
```

#### 3. `questions` Table
```sql
ALTER TABLE `questions` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' NOT NULL AFTER `question_text`;
```

#### 4. `tests` Table
```sql
-- Add source field
ALTER TABLE `tests` 
ADD COLUMN `source` ENUM('SC Pro', 'CERC') DEFAULT 'SC Pro' NOT NULL AFTER `description`;

-- Add sc_pro_test_id field (for CERC tests to link to SC Pro tests)
ALTER TABLE `tests` 
ADD COLUMN `sc_pro_test_id` BIGINT UNSIGNED NULL AFTER `source`,
ADD CONSTRAINT `tests_sc_pro_test_id_foreign` 
    FOREIGN KEY (`sc_pro_test_id`) 
    REFERENCES `tests` (`id`) 
    ON DELETE SET NULL;
```

---

## Running Migrations

### Run All Migrations
```bash
php artisan migrate
```

### Run Specific Migration
```bash
php artisan migrate --path=database/migrations/2026_02_11_060102_add_source_to_questions_clusters_constructs_tests_tables.php
php artisan migrate --path=database/migrations/2026_02_11_063248_add_sc_pro_test_id_to_tests_table.php
```

### Rollback Migrations
```bash
# Rollback last batch
php artisan migrate:rollback

# Rollback specific migration
php artisan migrate:rollback --step=1
```

---

## Data Migration (Optional)

### Update Existing Records

After running migrations, existing records will have `source = 'SC Pro'` by default. If you need to update specific records:

```sql
-- Update specific tests to CERC (example)
UPDATE `tests` 
SET `source` = 'CERC', `sc_pro_test_id` = 1 
WHERE `id` = 2;

-- Update specific questions to CERC (example)
UPDATE `questions` 
SET `source` = 'CERC' 
WHERE `id` IN (101, 102, 103);

-- Update specific clusters to CERC (example)
UPDATE `clusters` 
SET `source` = 'CERC' 
WHERE `id` = 5;

-- Update specific constructs to CERC (example)
UPDATE `constructs` 
SET `source` = 'CERC' 
WHERE `id` IN (10, 11, 12);
```

---

## Verification Queries

### Check if columns exist
```sql
-- Check source columns
SHOW COLUMNS FROM `clusters` LIKE 'source';
SHOW COLUMNS FROM `constructs` LIKE 'source';
SHOW COLUMNS FROM `questions` LIKE 'source';
SHOW COLUMNS FROM `tests` LIKE 'source';

-- Check sc_pro_test_id column
SHOW COLUMNS FROM `tests` LIKE 'sc_pro_test_id';
```

### Check foreign key constraint
```sql
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tests'
    AND CONSTRAINT_NAME = 'tests_sc_pro_test_id_foreign';
```

### Verify test mappings
```sql
-- Get all CERC tests with their linked SC Pro tests
SELECT 
    cerc.id AS cerc_test_id,
    cerc.title AS cerc_test_title,
    cerc.source AS cerc_source,
    scpro.id AS sc_pro_test_id,
    scpro.title AS sc_pro_test_title,
    scpro.source AS sc_pro_source
FROM 
    `tests` AS cerc
LEFT JOIN 
    `tests` AS scpro ON cerc.sc_pro_test_id = scpro.id
WHERE 
    cerc.source = 'CERC';
```

---

## Summary

### New Columns Added

| Table | Column | Type | Default | Nullable | Purpose |
|-------|--------|------|---------|----------|---------|
| `clusters` | `source` | ENUM('SC Pro', 'CERC') | 'SC Pro' | No | Differentiate cluster source |
| `constructs` | `source` | ENUM('SC Pro', 'CERC') | 'SC Pro' | No | Differentiate construct source |
| `questions` | `source` | ENUM('SC Pro', 'CERC') | 'SC Pro' | No | Differentiate question source |
| `tests` | `source` | ENUM('SC Pro', 'CERC') | 'SC Pro' | No | Differentiate test source |
| `tests` | `sc_pro_test_id` | BIGINT UNSIGNED | NULL | Yes | Link CERC test to SC Pro test |

### Foreign Keys Added

| Table | Column | References | On Delete |
|-------|--------|-----------|----------|
| `tests` | `sc_pro_test_id` | `tests.id` | SET NULL |

---

## Notes

1. **Backward Compatibility:** All existing records default to `'SC Pro'`, so existing data remains valid.

2. **CERC Test Requirement:** When creating CERC tests, `sc_pro_test_id` should be provided to explicitly link to an SC Pro test.

3. **Cascade Behavior:** If an SC Pro test is deleted, the `sc_pro_test_id` in linked CERC tests will be set to NULL (not deleted).

4. **Multiple Tests Support:** The `sc_pro_test_id` field allows multiple SC Pro and CERC tests per age group with explicit mapping.

---

## Migration Order

1. **First:** Run `2026_02_11_060102_add_source_to_questions_clusters_constructs_tests_tables.php`
   - Adds source field to all tables

2. **Second:** Run `2026_02_11_063248_add_sc_pro_test_id_to_tests_table.php`
   - Adds sc_pro_test_id field (depends on tests table having source field)

---

## Testing After Migration

1. Verify all columns exist:
   ```bash
   php artisan tinker
   >>> Schema::hasColumn('tests', 'source')
   => true
   >>> Schema::hasColumn('tests', 'sc_pro_test_id')
   => true
   ```

2. Test creating SC Pro test:
   ```php
   Test::create([
       'title' => 'SC Pro Test',
       'source' => 'SC Pro',
       'is_active' => true
   ]);
   ```

3. Test creating CERC test with mapping:
   ```php
   $scPro = Test::where('source', 'SC Pro')->first();
   Test::create([
       'title' => 'CERC Test',
       'source' => 'CERC',
       'sc_pro_test_id' => $scPro->id,
       'is_active' => true
   ]);
   ```

---

## Rollback Instructions

If you need to rollback these changes:

```bash
# Rollback in reverse order
php artisan migrate:rollback --step=1  # Rollback sc_pro_test_id
php artisan migrate:rollback --step=1  # Rollback source fields
```

Or rollback all:
```bash
php artisan migrate:rollback
```

