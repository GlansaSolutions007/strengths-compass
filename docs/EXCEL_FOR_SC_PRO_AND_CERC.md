# How to Create Excel Files for SC Pro and CERC Tests (No Duplication)

This guide explains **how to build the Excel files** for both tests and **how to assign clusters and constructs** so you avoid duplicating questions or constructs in the database, while still allowing the **same construct to belong to different clusters** in different tests.

---

## 1. What one row means

Each row in the Excel defines **one question** in the test and its **assignment** for that test:

| Column     | Meaning for this test |
|-----------|------------------------|
| **Cluster**   | For this test, this question belongs to this **cluster**. |
| **Construct** | For this test, this question measures this **construct**, and this construct is in the cluster above. |
| **Question**  | The question text (reused if same text + construct already exists, or created once). |
| **Category**  | P, R, or SDB. |

So:

- **Assigning a construct to a cluster** = put that **cluster name** in column A and **construct name** in column B in the same row.
- **Same construct in a different cluster in another test** = in the **other test’s Excel**, use a row with a **different cluster** and the **same construct name**. No duplicate construct or question is created; only the **test-specific** assignment (cluster + construct) is stored.

---

## 2. Column layout (same for both tests)

Use **exactly** these column headers in the first row (spelling and order matter for import):

| A        | B         | C         | D        | E (optional) | F (optional) |
|----------|-----------|-----------|----------|--------------|--------------|
| Cluster  | Construct | Question  | Category | Question ID  | Source       |

- **Cluster** – Required. Exact name of the cluster for **this test** (must exist for the age group).
- **Construct** – Required. Construct name (reused if it already exists; otherwise created once).
- **Question** – Required. Full question text (reused if same text + same construct exists; otherwise created once).
- **Category** – Required. One of: **P**, **R**, **SDB**.
- **Question ID** – Optional. If you put an existing question ID here, that question is **reused** (no new question row in DB). Mainly useful in CERC to reuse SC Pro questions by ID.
- **Source** – Optional. `SC Pro` or `CERC`. Usually omitted; the test type is set when you create the test in the app.

---

## 3. How to create the SC Pro Excel

### Step 1: Get cluster and construct names

- Use the **download template** from the app:  
  `GET /api/tests/questions/template?age_group_id=<your_age_group_id>`  
  This gives you an Excel with **Construct** prefilled (and **Cluster** empty) for that age group.
- Or get clusters from: `GET /api/clusters` (optionally filtered by age group). Construct names can come from your master data or from the template.

### Step 2: Decide assignment (SC Pro)

For SC Pro, you decide which **constructs** belong to which **clusters** for this test. Example:

- Cluster “Emotional Regulation” → constructs: Resilience, Self-Compassion, …
- Cluster “Cognitive” → constructs: Critical Thinking, Creativity, …

So in the Excel:

- Rows for “Emotional Regulation” will have **Cluster** = `Emotional Regulation` and **Construct** = e.g. `Resilience`.
- Rows for “Cognitive” will have **Cluster** = `Cognitive` and **Construct** = e.g. `Critical Thinking`.

**One row = one question.** So if “Resilience” has 3 questions, you add 3 rows with the same Cluster and Construct and different Question text (and Category P/R/SDB).

### Step 3: Fill the sheet

1. **Row 1:** Headers: `Cluster`, `Construct`, `Question`, `Category` (and optionally `Question ID`, `Source`).
2. **From row 2:** For each question:
   - **Cluster** = cluster name for this test (e.g. `Emotional Regulation`).
   - **Construct** = construct name (e.g. `Resilience`).
   - **Question** = exact question text (e.g. `I bounce back quickly from setbacks`).
   - **Category** = `P`, `R`, or `SDB`.

Use **exact** cluster and construct names (as in the app/template); matching is case-insensitive but spelling must match.

### Step 4: Save and import

- Save as **.xlsx** (or .xls / .csv if supported).
- Create the SC Pro test via API with this file:  
  `POST /api/tests` with `questions_file` = this Excel, `source` = `SC Pro`, plus title, age_group_id, etc.

**No duplication:** Constructs are matched by **name** (reused if already in DB). Questions are matched by **question text + construct** (reused if that combination exists). So the same names/texts in another sheet or test will not create duplicate constructs or questions.

---

## 4. How to create the CERC Excel (reuse + different cluster assignment)

CERC can:

- Reuse **constructs** (same names as SC Pro or existing master data).
- Reuse **questions** (same question text + construct, or use **Question ID**).
- Assign the **same construct to a different cluster** than in SC Pro (per-row assignment in this Excel only).

### Step 1: Use the same column layout

Same as SC Pro: **Cluster**, **Construct**, **Question**, **Category**, and optionally **Question ID**, **Source**.

### Step 2: Assign clusters and constructs for CERC

For CERC you may want a **different** cluster–construct mapping than SC Pro. Example:

- In **SC Pro** you had: Cluster “Emotional Regulation” → Construct “Resilience”.
- In **CERC** you want: Cluster “CERC Cluster A” → Construct “Resilience”.

So in the **CERC** Excel you put:

- **Cluster** = `CERC Cluster A` (or whatever cluster name exists for CERC).
- **Construct** = `Resilience` (same name = **reuse** construct; no duplicate).

That’s how you “assign” the same construct to a different cluster for CERC: **same construct name, different cluster name in the row.**

### Step 3: Reuse questions (no duplicate questions)

Two ways:

**Option A – Same question text + construct (no Question ID)**  
- Use the **exact same** Question text and Construct name as in SC Pro (or in master data).  
- Import will find that question and **reuse** it (one row in DB), and attach it to the CERC test with the cluster/construct from this row.

**Option B – Use Question ID (best for “same question as SC Pro”)**  
1. Get question IDs from SC Pro (e.g. from `GET /api/tests/{sc_pro_test_id}/questions`; each question has `id`).  
2. In the CERC Excel, add a **Question ID** column.  
3. For each row that should reuse an SC Pro question, put that question’s **id** in **Question ID**.  
4. You can still fill **Cluster**, **Construct**, **Category** for CERC (and optionally leave **Question** empty or repeat the text for readability; if Question ID is present and valid, the import uses that question and does not create a new one).

So:

- **Reuse question:** same (question text + construct) **or** provide **Question ID**.
- **Reuse construct:** same **Construct** name.
- **Different cluster for same construct:** put the **CERC cluster name** in **Cluster** and the same construct name in **Construct** in the CERC Excel.

### Step 4: New CERC-only rows

For questions that exist **only** in CERC:

- **Cluster** = CERC cluster name.  
- **Construct** = existing construct name (reuse) or **new** construct name (will be created once).  
- **Question** = new text (will create one new question if that text + construct doesn’t exist).  
- **Category** = P / R / SDB.  
- Leave **Question ID** empty.

### Step 5: Save and import

- Save as .xlsx (or supported format).
- Create the CERC test via API:  
  `POST /api/tests` with `questions_file` = this Excel, `source` = `CERC`, **`sc_pro_test_id`** = &lt;SC Pro test id&gt;, plus title, age_group_id, etc.

**No duplication:**  
- Same construct name → same construct in DB.  
- Same question (by ID or by text+construct) → same question in DB.  
- Only the **per-test** assignment (which cluster/construct each question belongs to for CERC) is stored; it does not change SC Pro or create duplicate questions/constructs.

---

## 5. Assigning clusters and constructs (summary)

- **One row** = one question in the test + for **this test**, this question is in **this cluster** and measures **this construct**.
- **Same construct, different cluster in another test:**  
  In the **other test’s** Excel, use a row with the **other cluster name** and the **same construct name**. No duplicate construct is created.
- **Same question in both tests:**  
  In CERC Excel either use the same **Question** text + **Construct** or set **Question ID** to the existing question’s id. No duplicate question is created.
- **Prevent duplication:**  
  - Constructs: use **exact same name** across tests to reuse.  
  - Questions: reuse by **Question ID** or by **same (Question text + Construct)**.

---

## 6. Example: SC Pro vs CERC assignment

**SC Pro Excel (excerpt):**

| Cluster              | Construct   | Question                          | Category |
|----------------------|------------|------------------------------------|----------|
| Emotional Regulation | Resilience | I bounce back quickly from setbacks | P        |
| Emotional Regulation | Resilience | I find it hard to recover from stress | R      |
| Cognitive            | Creativity | I often think of new ideas         | P        |

**CERC Excel (excerpt) – same construct “Resilience” in a different cluster:**

| Cluster      | Construct   | Question                          | Category | Question ID |
|-------------|------------|------------------------------------|----------|-------------|
| CERC Wellbeing | Resilience | I bounce back quickly from setbacks | P      | 101         |
| CERC Wellbeing | Resilience | I find it hard to recover from stress | R     | 102         |
| CERC Innovation | Creativity | I often think of new ideas         | P        | 103         |

Here:

- 101, 102, 103 are the question IDs from the SC Pro test (or from the questions API).
- Constructs “Resilience” and “Creativity” are **reused** (same names).
- Questions are **reused** via **Question ID** (no duplicate questions).
- For CERC, “Resilience” is assigned to cluster **CERC Wellbeing** and “Creativity” to **CERC Innovation**; for SC Pro they were in “Emotional Regulation” and “Cognitive”. Assignment is **per test**; no duplication of data.

---

## 7. Getting question IDs for the CERC Excel

1. After SC Pro is created, call:  
   `GET /api/tests/{sc_pro_test_id}/questions`  
   Response contains a list of questions; each has `id`, `question_text`, `construct_id`, `category`, etc.
2. Build a mapping (e.g. in a sheet or script): question text (and construct) → `id`.
3. In the CERC Excel, set the **Question ID** column to that `id` for each row that should reuse that question.

You can also export the SC Pro test’s questions to a small reference sheet (e.g. Question ID, Question text, Construct) and use that to fill the CERC Excel.

---

## 8. Checklist before import

- [ ] First row is the header row with: Cluster, Construct, Question, Category (and optionally Question ID, Source).
- [ ] Cluster names match exactly the names in the app for the test’s age group.
- [ ] Construct names are spelled consistently (same name = reuse; no duplicate construct).
- [ ] Category is only P, R, or SDB.
- [ ] For CERC reuse: either same (Question + Construct) or Question ID filled with existing question id.
- [ ] File saved as .xlsx (or allowed format), max 10 MB.

This way you create Excel files for **both** tests and **assign** clusters and constructs **per test** without duplicating questions or constructs in the database.
