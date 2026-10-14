# Manual Test Cases

These cases verify that the election pages display candidate data from the configured MySQL database. Run them with the PHP app and MySQL running. They do not submit a ballot or change voting results.

## TC-DB-01: Per-block candidate data appears on the matching ballot

**Purpose:** Confirm that `vote.php` shows candidate data from `block_candidates` for the signed-in student's assigned block and election.

**Preconditions:** The student account is active, verified, assigned to a block, and that block has an open election with an active candidate. The candidate's `photo_path` points to an image under `public/`, such as `assets/img/candidates/candidate-a.svg`.

**Steps:**

1. In the MySQL database configured in `.env`, find an active candidate for an open block election:

   ```sql
   SELECT b.section, e.id AS election_id, p.id AS position_id,
          c.id AS candidate_id, c.full_name, c.party,
          c.credentials, c.platform, c.photo_path
   FROM block_elections e
   JOIN blocks b ON b.id = e.block_id
   JOIN block_positions p ON p.election_id = e.id
   JOIN block_candidates c ON c.position_id = p.id
   WHERE e.status = 'open' AND c.is_active = 1
   ORDER BY e.id, p.sort_order, c.id
   LIMIT 1;
   ```

2. Note `candidate_id` and update that row, replacing `123` with its ID:

   ```sql
   UPDATE block_candidates
   SET full_name = 'Database Reflection Test Candidate',
       party = 'Database Test Party',
       credentials = 'Database test credential',
       platform = 'Database test platform',
       photo_path = 'assets/img/candidates/candidate-a.svg'
   WHERE id = 123;
   ```

3. Sign in as a student assigned to the returned section and open `vote.php`.
4. Confirm the candidate appears under the correct position. Change one field in MySQL and reload the page.

**Expected result:** The ballot shows the database name, party, credentials, platform, and portrait. After a database update and page reload, the new value appears without editing JavaScript or HTML.

**Cleanup:** Restore the original values recorded in step 1, or keep the sample row as test data.

## TC-DB-02: CICS-wide candidate data appears on the college ballot

**Purpose:** Confirm that `cics-vote.php` shows candidate data from `cics_candidates` for the configured CICS election.

**Preconditions:** The student account is active, verified, assigned to a CICS block, and a CICS election has an active candidate.

**Steps:**

1. Find a CICS candidate:

   ```sql
   SELECT e.id AS election_id, p.id AS position_id,
          c.id AS candidate_id, c.full_name, c.party,
          c.credentials, c.platform, c.photo_path
   FROM cics_elections e
   JOIN cics_positions p ON p.election_id = e.id
   JOIN cics_candidates c ON c.position_id = p.id
   WHERE c.is_active = 1
   ORDER BY e.election_year DESC, e.id DESC, p.sort_order, c.id
   LIMIT 1;
   ```

2. Note `candidate_id` and update the row, replacing `123` with its ID:

   ```sql
   UPDATE cics_candidates
   SET full_name = 'CICS Database Reflection Test Candidate',
       party = 'CICS Database Test Party',
       credentials = 'CICS database test credential',
       platform = 'CICS database test platform',
       photo_path = 'assets/img/candidates/candidate-b.svg'
   WHERE id = 123;
   ```

3. Sign in as an eligible student and open `cics-vote.php`.
4. Confirm the candidate appears under the correct position. Change a field in MySQL and reload the page.

**Expected result:** The CICS ballot shows the updated database values and portrait after reload without a frontend code change.

**Cleanup:** Restore the original values recorded in step 1, or keep the sample row as test data.

## TC-DB-03: Candidate image path resolves in the browser

**Purpose:** Confirm the stored `photo_path` points to a publicly served file.

**Steps:**

1. Read `photo_path` from the candidate table used by the page under test.
2. Open `http://localhost:8000/<photo_path>` (for example, `http://localhost:8000/assets/img/candidates/candidate-a.svg`).

**Expected result:** The browser returns HTTP 200 and displays the image; the same portrait appears on the candidate card. Store a path relative to `public/`, not an absolute filesystem path such as `C:\xampp\htdocs\...`.

## TC-UI-01: Candidate dashboard renders in a browser

**Purpose:** Verify that both voting pages render the data-driven candidate cards and sample portraits in a real browser.

**Steps:**

1. Sign in as an eligible demo student.
2. Open `vote.php` and check the election summary, positions, candidate names, credentials, platforms, portraits, and ballot controls.
3. Open `cics-vote.php` and check the same candidate details plus election year and the student's block, year, and semester.

**Expected result:** Both pages load without a server error. Candidate portraits appear beside their database-backed details. The CICS summary displays actual student block/year/semester values and has no `undefined` fields.

**Run result:** PASS on 2026-10-14 in Chrome at 1440 × 1800. Both pages rendered with six candidate portraits; the CICS summary displayed `BSIT 1202`, `1st Year`, and `Semester 2`. No ballot was submitted.

## Test record

| Case | Result | Date / tester | Notes |
| --- | --- | --- | --- |
| TC-DB-01 | PASS | 2026-10-14 / Codex | Page and ballot API passed; MySQL values were returned, then original candidate row restored. No ballot submitted. |
| TC-DB-02 | PASS | 2026-10-14 / Codex | CICS page and ballot API passed; MySQL values were returned, then original candidate row restored. No ballot submitted. |
| TC-DB-03 | PASS | 2026-10-14 / Codex | All six sample candidate SVGs returned HTTP 200 with image/svg+xml. |
| TC-UI-01 | PASS | 2026-10-14 / Codex | Chrome rendered per-block and CICS-wide pages with candidate portraits and details. No ballot submitted. |
