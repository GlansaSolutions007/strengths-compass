# Clusters → Constructs → Questions → Tests → Reports

**Full documentation of the Strengths Compass data model and workflow**, from master data (clusters, constructs, questions) through test setup, scoring, and reports.

---

## Table of contents

1. [Conceptual model](#1-conceptual-model)
2. [Database structure](#2-database-structure)
3. [Master data: clusters, constructs, questions](#3-master-data-clusters-constructs-questions)
4. [Tests: setup and Excel workflow](#4-tests-setup-and-excel-workflow)
5. [Scoring: how cluster and construct scores are calculated](#5-scoring-how-cluster-and-construct-scores-are-calculated)
6. [Reports: how cluster and construct details are built](#6-reports-how-cluster-and-construct-details-are-built)
7. [How to proceed: step-by-step](#7-how-to-proceed-step-by-step)
8. [Quick reference](#8-quick-reference)

---

## 1. Conceptual model

### 1.1 Independence of clusters and constructs

- **Clusters** and **constructs** are **independent** in the system.
- A **construct** is not globally tied to one cluster. The same construct (e.g. "Resilience") can appear in **different clusters in different tests**.
- The link **“which construct belongs to which cluster”** is defined **per test** when you build the test (e.g. via the test Excel).

So:

- **Master data:** clusters and constructs exist on their own (optionally with `age_group_id`, `source`, etc.).
- **Per test:** you assign constructs to clusters via the test Excel (and the system stores this in `test_cluster_construct`).

### 1.2 Questions

- **Questions** belong to a **construct** (`questions.construct_id`).
- A question is a single item (e.g. “I bounce back quickly from setbacks”) and is linked to one construct only.
- When a question is used in a **test**, the test also stores **which cluster** that question belongs to **in that test** (in `test_question.cluster_id`). So for scoring and reports, “cluster” comes from the test’s assignment, not from the construct’s (optional) global `cluster_id`.

### 1.3 Flow in one sentence

**Master data:** Clusters + Constructs (independent) → Questions (each question → one construct).  
**Per test:** You assign clusters to the test, and for that test you assign constructs to clusters and add questions (with cluster + construct + category).  
**Scoring/Reports:** Use the test’s assignments (`test_question.cluster_id`, `test_cluster_construct`) to compute cluster/construct scores and to show the right cluster–construct structure in reports.

---

## 2. Database structure

### 2.1 Master data tables

| Table         | Purpose |
|--------------|---------|
| `clusters`   | Cluster definitions (name, description, area, behaviours, age_group_id, source, etc.). |
| `constructs` | Construct definitions (name, description, behaviours, age_group_id, etc.). `cluster_id` is **nullable**: constructs are independent of clusters; it may still be set for legacy or reference. |
| `questions`  | Question text, linked to one **construct** (`construct_id`), with `category` (P / R / SDB), age_group, source, etc. |

### 2.2 Test-related pivot tables

| Table                   | Columns (main) | Purpose |
|-------------------------|----------------|---------|
| `test_cluster`          | test_id, cluster_id, p_count, r_count, sdb_count | Which **clusters** are in the test; optional category counts for auto-selection. |
| `test_cluster_construct` | test_id, cluster_id, construct_id (unique per test_id + construct_id) | **Per test:** which construct is in which cluster. This is the source of truth for “construct X is in cluster Y for this test”. |
| `test_question`         | test_id, question_id, cluster_id, order_no | Which **questions** are in the test and, for each, which **cluster** they belong to **in that test**. |

Relationships in short:

- **Test** → many **clusters** via `test_cluster`.
- **Test** → many **constructs** (with per-cluster assignment) via `test_cluster_construct` (pivot includes `cluster_id`).
- **Test** → many **questions** via `test_question` (pivot includes `cluster_id` for scoring/reports).

### 2.3 Test result and reporting

| Table / field      | Purpose |
|--------------------|--------|
| `test_results`     | One row per user per test attempt (e.g. status, overall_category, average_score). |
| `cluster_scores`   | JSON on test_result: cluster name → { total, average, percentage, count, category, area }. |
| `construct_scores` | JSON on test_result: construct name → { total, average, percentage, count, category }. |

Reports and emails build **cluster details** and **construct details** from the **test’s** `test_cluster_construct` (and, if needed, from `test->clusters` and legacy `cluster->constructs`).

---

## 3. Master data: clusters, constructs, questions

### 3.1 Clusters

- Created/managed via Cluster API (or seeds).
- Typical fields: `name`, `short_code`, `description`, `area`, `age_group_id`, `high_behaviour`, `medium_behaviour`, `low_behaviour`, `is_active`, `source`.
- Used in tests by **attaching** them to a test (via `test_cluster`). The test then shows those clusters in reports; which constructs sit under each cluster for that test comes from `test_cluster_construct`.

### 3.2 Constructs

- Created/managed via Construct API (or seeds).
- **No requirement** to set `cluster_id`. If set, it can be used for legacy behaviour or for UI hints; the **authoritative** assignment for a test is always `test_cluster_construct`.
- Typical fields: `name`, `short_code`, `description`, `definition`, behaviours, `age_group_id`, `display_order`, `is_active`, `source`.

### 3.3 Questions

- Each question has **one** `construct_id` (the construct it measures).
- `category`: **P** (Positive), **R** (Reverse), or **SDB** (Social Desirability Bias). P and R are used for cluster/construct scoring; SDB is used for flagging, not for cluster/construct averages.
- Questions are **reusable** across tests: the same question can appear in different tests, under different clusters, as long as the test’s Excel (or manual assignment) assigns it to a cluster for that test.

---

## 4. Tests: setup and Excel workflow

### 4.1 Creating a test

- Create a **Test** (title, description, age_group_id, source e.g. “SC Pro” or “CERC”, optional `sc_pro_test_id` for CERC).
- Attach **clusters** to the test (e.g. via API: test → clusters with optional p_count, r_count, sdb_count).
- Add **questions** to the test in one of two ways:
  - **Excel upload** (recommended): one row = one question, with Cluster, Construct, Question text, Category. This drives both `test_question` and `test_cluster_construct`.
  - **Manual selection**: pick existing questions; the system infers `cluster_id` for each from `test_cluster_construct` or from construct’s cluster (legacy).

### 4.2 Excel template (test questions)

- **Download:** e.g. “Download test questions template” for an age group.
- **Columns:** Cluster | Construct | Question | Category.
- **Template behaviour:** Lists **constructs** for the age group; **Cluster** is left empty so that **you** choose the cluster per row. This reflects that cluster–construct assignment is **per test** and defined in the sheet.
- Fill:
  - **Cluster:** exact name of a cluster that belongs to the test’s age group (and will be attached to the test if not already).
  - **Construct:** exact name of a construct (looked up by name, independent of cluster).
  - **Question:** question text (new question is created and linked to that construct).
  - **Category:** P, R, or SDB.

### 4.3 What happens on import (per row)

1. **Cluster** is resolved by name (and age group if applicable). The cluster is attached to the test via `test_cluster` if not already.
2. **Construct** is resolved by **name only** (and optional age group). No “construct must belong to this cluster” check at master level.
3. **test_cluster_construct:** A row `(test_id, cluster_id, construct_id)` is inserted (if not already present). This defines “for this test, this construct is in this cluster.”
4. **Question:** A new question is created with that `construct_id`, question text, and category (or an existing question could be used if your process reuses questions; the import as implemented creates new questions).
5. **test_question:** A row `(test_id, question_id, cluster_id, order_no)` is added so the question appears in the test and is tied to the correct **cluster for this test**.

So: one Excel row = one question in the test + the right cluster for that question in this test + the right construct–cluster assignment for this test.

### 4.4 Multiple tests (e.g. SC Pro and CERC)

- You can have several tests (e.g. “SC Pro – Adults”, “CERC – Adults”).
- The **same construct** can be in **different clusters** in different tests: e.g. in Test A construct “Resilience” is in cluster “Emotional”, in Test B it is in cluster “Cognitive”. Each test has its own `test_cluster_construct` rows.
- Scoring and reports always use the **test’s** assignments (`test_question.cluster_id` and `test_cluster_construct`), so previous data and other tests are not affected.

---

## 5. Scoring: how cluster and construct scores are calculated

### 5.1 When scoring runs

- On test submission, the system computes **cluster_scores** and **construct_scores** and stores them on the **test_result** (JSON).

### 5.2 Cluster scores

- For each **answer** (question_id + response), the system needs a **cluster** for that question **in this test**.
  - **Primary:** `cluster_id` from **test_question** (the pivot row for this test_id + question_id). This is the test-specific cluster for that question.
  - **Fallback:** If pivot has no cluster_id (e.g. old data), it uses `question->construct->cluster` (legacy).
- Only **P** and **R** questions are included in cluster (and construct) averages; **SDB** is excluded from those averages.
- For each cluster, the system sums the (weighted/reversed) scores and counts items, then computes total, average, percentage (1–5 scale → 0–100%), and category (e.g. low / medium / high). Result is stored in `test_result.cluster_scores` keyed by **cluster name**.

### 5.3 Construct scores

- Each question has a **construct** (`questions.construct_id`). Construct name is taken from `question->construct`.
- Same inclusion rules: only P and R; same aggregation (totals, averages, percentages, categories). Result is stored in `test_result.construct_scores` keyed by **construct name**.

So: **cluster** grouping is test-driven (from `test_question.cluster_id` + fallback); **construct** grouping is from the question’s construct. Scoring logic itself (weights, reverse scoring, categories) is unchanged; only the source of “which cluster” is test-specific.

---

## 6. Reports: how cluster and construct details are built

### 6.1 Cluster details (for this test)

- **Source:** The test’s clusters (`test->clusters`) and, for each cluster, the **constructs in that cluster for this test** from **test_cluster_construct** (construct_ids where test_id and cluster_id match).
- **Fallback:** If there are no `test_cluster_construct` rows for the test, the report falls back to **cluster->constructs** (legacy).
- **Output:** List of clusters with names, descriptions, behaviours, and list of constructs (with names, descriptions, behaviours) for each cluster, so the report shows the **test’s** structure.

### 6.2 Construct details and enrichment

- **Construct → cluster name:** For each construct that appears in the test result, the report needs “which cluster it belongs to **in this test**”. That comes from **test_cluster_construct** (construct_id → cluster_id for this test_id), then cluster name from `test->clusters`.
- **Fallback:** If no pivot row, use legacy (e.g. construct->cluster or test->clusters->constructs).
- **Enrichment:** Stored scores (`cluster_scores`, `construct_scores`) are enriched with descriptions and behaviours from the test’s cluster/construct data (again using test_cluster_construct where available).

So reports and emails always prefer **test-specific** cluster–construct mapping from `test_cluster_construct`, then fall back to legacy so old data still looks correct.

---

## 7. How to proceed: step-by-step

### Step 1: Master data (once or per age group/source)

1. **Clusters:** Create clusters (e.g. via API or admin). Set name, description, area, behaviours, age_group_id, source as needed.
2. **Constructs:** Create constructs (name, description, behaviours, age_group_id, etc.). You do **not** need to set `cluster_id`; the link to clusters will be per test.
3. **Questions (optional here):** You can create questions linked to constructs in advance, or create them during test import (one new question per Excel row with that construct).

### Step 2: Create a test

1. Create the test (title, age group, source e.g. “SC Pro” or “CERC”).
2. Attach **clusters** to the test (the clusters that this test will use). Optional: set p_count, r_count, sdb_count per cluster for auto question selection.

### Step 3: Add questions to the test (Excel recommended)

1. **Download** the test-questions template (for the test’s age group).
2. **Fill** rows:
   - **Cluster:** cluster name for this test (must exist and match the test’s age group).
   - **Construct:** construct name (independent of cluster).
   - **Question:** question text (will create a new question for that construct if your flow does so).
   - **Category:** P, R, or SDB.
3. **Upload** the Excel to the test (e.g. POST test update with file). The system will:
   - Resolve cluster and construct by name.
   - Insert/ensure `test_cluster_construct` (test_id, cluster_id, construct_id).
   - Create/link questions and insert `test_question` (test_id, question_id, cluster_id, order_no).
   - Attach clusters to the test if not already.

For a **second test** (e.g. another SC Pro or CERC), repeat from Step 2. You can reuse the same construct names and assign them to **different** clusters in the new test’s Excel; the new test will have its own `test_cluster_construct` and `test_question` rows.

### Step 4: Users take the test

- Questions and options are served according to `test_question` and test settings.
- On submit, **scoring** uses `test_question.cluster_id` (and `test_cluster_construct` only indirectly via how questions were assigned) to compute cluster_scores and construct_scores and save them on the test_result.

### Step 5: Reports and emails

- When generating a report or email, the app builds **cluster details** and **construct details** from the **test’s** `test_cluster_construct` and `test->clusters` (with legacy fallback).
- Enrichment of scores with descriptions and behaviours uses the same test-specific mapping, so the report reflects the structure of **that** test.

---

## 8. Quick reference

| Goal | Where it’s defined / stored |
|------|-----------------------------|
| Which clusters exist | `clusters` table |
| Which constructs exist | `constructs` table (no required link to cluster) |
| Which construct a question measures | `questions.construct_id` |
| Which clusters a test uses | `test_cluster` (test_id, cluster_id) |
| Which construct is in which cluster **for a test** | `test_cluster_construct` (test_id, cluster_id, construct_id) |
| Which questions are in a test and in which cluster | `test_question` (test_id, question_id, cluster_id, order_no) |
| Cluster scores for a result | `test_results.cluster_scores` (JSON), computed using `test_question.cluster_id` |
| Construct scores for a result | `test_results.construct_scores` (JSON), from question->construct |
| Report “constructs per cluster” | From `test_cluster_construct` for that test (then legacy) |
| Report “cluster name per construct” | From `test_cluster_construct` for that test (then legacy) |

### Excel columns (test questions)

| Column   | Meaning | Example |
|----------|---------|---------|
| Cluster  | Cluster name for **this test** (must exist) | "Emotional Regulation" |
| Construct| Construct name (looked up by name; can be in different clusters in other tests) | "Resilience" |
| Question | Question text (creates/uses question for that construct) | "I bounce back quickly from setbacks" |
| Category | P, R, or SDB | P |

### Important points

- **Cluster and construct are independent** at master level; their relationship is **per test** via the Excel and `test_cluster_construct`.
- **Same construct in different tests** can sit in different clusters; each test has its own mapping.
- **Scoring** uses the test’s `test_question.cluster_id` for cluster grouping (with legacy fallback).
- **Reports** use `test_cluster_construct` for the test’s cluster–construct structure (with legacy fallback).
- **Existing data** remains valid: backfill has populated `test_cluster_construct` from previous cluster–construct links, and legacy paths are still supported.

---

*Last updated to reflect the independent cluster–construct model and test-specific assignment (test_cluster_construct, test_question.cluster_id).*
