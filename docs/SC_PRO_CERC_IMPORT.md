# SC Pro and CERC Test Import (No Duplication)

**For step-by-step Excel creation and how to assign clusters/constructs without duplication, see [EXCEL_FOR_SC_PRO_AND_CERC.md](EXCEL_FOR_SC_PRO_AND_CERC.md).**

## Overview

- **SC Pro** test is created first via Excel: Cluster → Construct → Question → Category.
- **CERC** test is created later via another Excel. It can reuse constructs and questions from SC Pro and add new ones, with **test-specific** cluster–construct assignments.

## Rules (no duplication)

| Entity     | Rule |
|-----------|------|
| **Constructs** | Reuse by **name** (case-insensitive). If not found, **create** (no duplicate names). |
| **Questions**  | Reuse by **Question ID** (if provided in Excel), or by **same question text + same construct**. If not found, **create** (no duplicate question text per construct). |
| **Cluster–construct** | Stored only in **test_cluster_construct** per test. Same construct can be in different clusters in SC Pro vs CERC. |

## Excel columns

| Column        | Required | Description |
|---------------|----------|-------------|
| Cluster       | Yes      | Cluster name for **this test**. |
| Construct     | Yes      | Construct name (reused if exists, else created). |
| Question      | Yes      | Question text (reused if same text + construct exists, else created). |
| Category      | Yes      | P, R, or SDB. |
| Question ID   | No       | If provided and exists, this question is **reused** and no new question is created. Use for CERC when reusing SC Pro questions. |
| Source        | No       | SC Pro / CERC. |

## CERC behaviour

1. **Every row is processed** and attached to the CERC test (`test_question` + `test_cluster_construct`). Rows are **not** skipped when the question already exists in SC Pro.
2. **Reuse:** If the row has an existing **Question ID** or the same **question text + construct** exists, that question is reused and linked to the CERC test with the CERC cluster/construct from the row.
3. **“Don’t show again”** is only at **test-taking** time: when a user takes the CERC test, questions already answered in SC Pro are **filtered out** from the list. The database still has all CERC questions (including shared ones) linked to the CERC test so that:
   - Reporting uses the correct CERC cluster/construct for every question.
   - Combined SC Pro + CERC answers are mapped correctly.

## Import stats

After import, `getStats()` returns:

- `success` – number of rows processed and attached.
- `failures` – validation/processing errors.
- `created_count` – questions **created** in this import.
- `reused_count` – questions **reused** (by question_id or by text + construct).
- `created_questions` – list of `{ question_id, cluster_id }` for the controller to attach to the test (both new and reused).

## Summary

- **No duplicate questions:** Reuse by Question ID or by (question text + construct).
- **No duplicate constructs:** Reuse by name; create only when missing.
- **Cluster–construct is per test:** Stored in `test_cluster_construct`; does not affect other tests.
- **CERC:** All rows are attached to the CERC test; “common questions” are hidden only when taking the test, not removed from the CERC test structure.
