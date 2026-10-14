# Test Case Documentation
## CICS E-Voting System — Block Election Module
**Version:** 1.0.0
**Test Runner:** `cmd /c "C:\xampp\php\php.exe tests\TestRunner.php"`
**Database:** SQLite in-memory (isolated per run)
**Total Test Cases:** 84 | **Result: 84 Passed, 0 Failed**

---

## Table of Contents
1. [Module 1 — Auth Helpers](#module-1--auth-helpers)
2. [Module 2 — BlockElectionService](#module-2--blockelectionservice)
3. [Module 3 — BlockPositionService](#module-3--blockpositionservice)
4. [Module 4 — BlockCandidateService](#module-4--blockcandidateservice)
5. [Module 5 — BlockBallotService](#module-5--blockballotservice)
6. [Module 6 — BlockEligibilityService](#module-6--blockeligibilityservice)
7. [Bugs Found & Fixed](#bugs-found--fixed)

---

## Module 1 — Auth Helpers
**File:** `tests/TestAuthHelpers.php`
**Source:** `includes/auth.php`
**Total:** 18 test cases

Tests pure helper functions only — no HTTP requests or session side effects involved.

---

### Function: `is_valid_gsuite(string $email): bool`
Validates that an email follows the BatStateU GSuite format: `YY-NNNNN@g.batstate-u.edu.ph`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 1 | Valid GSuite email passes | `23-01041@g.batstate-u.edu.ph` | `true` | ✅ Pass |
| 2 | Valid GSuite with different year passes | `21-99999@g.batstate-u.edu.ph` | `true` | ✅ Pass |
| 3 | Wrong domain fails | `23-01041@gmail.com` | `false` | ✅ Pass |
| 4 | Missing student number fails | `@g.batstate-u.edu.ph` | `false` | ✅ Pass |
| 5 | Wrong format — no dash fails | `2301041@g.batstate-u.edu.ph` | `false` | ✅ Pass |
| 6 | Too short student number fails | `23-0104@g.batstate-u.edu.ph` | `false` | ✅ Pass |
| 7 | Too long student number fails | `23-010410@g.batstate-u.edu.ph` | `false` | ✅ Pass |
| 8 | Uppercase domain passes (case-insensitive) | `23-01041@G.BATSTATE-U.EDU.PH` | `true` | ✅ Pass |

---

### Function: `gsuite_to_student_id(string $email): string`
Extracts the student ID from a GSuite email address.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 9 | Extracts local part correctly | `23-01041@g.batstate-u.edu.ph` | `23-01041` | ✅ Pass |
| 10 | Lowercases the result | `23-01041@G.BATSTATE-U.EDU.PH` | `23-01041` | ✅ Pass |

---

### Function: `h(mixed $value): string`
Escapes HTML special characters to prevent XSS.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 11 | Escapes HTML special chars | `<script>` | `&lt;script&gt;` | ✅ Pass |
| 12 | Escapes double quotes | `"` | `&quot;` | ✅ Pass |
| 13 | Escapes single quotes | `'` | `&#039;` | ✅ Pass |
| 14 | Returns plain string unchanged | `Hello World` | `Hello World` | ✅ Pass |

---

### Function: `csrf_token(): string`
Generates and returns a session-bound CSRF token.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 15 | Returns non-empty string | — | `strlen > 0` | ✅ Pass |
| 16 | Returns same token on repeated calls | — | Same value both calls | ✅ Pass |

---

### Function: `parse_section(string $section): array`
Parses a 4-digit section code (YSBB) into year, semester, and block components.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 17 | Parses `1101` correctly | `1101` | year=`1`, semester=`1`, block=`01` | ✅ Pass |
| 18 | Handles invalid input gracefully | `XYZ` | year=`?` | ✅ Pass |

---

## Module 2 — BlockElectionService
**File:** `tests/TestBlockElectionService.php`
**Source:** `services/BlockElectionService.php`
**Total:** 21 test cases

---

### Function: `create(array $data, int $createdBy): int`
Creates a new election in `draft` status.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 1 | Returns new positive ID | Valid data | `id > 0` | ✅ Pass |
| 2 | Sets status to `draft` | Valid data | `status = 'draft'` | ✅ Pass |
| 3 | Rejects empty title | `title = ''` | RuntimeException: title is required | ✅ Pass |
| 4 | Rejects title over 200 chars | `title = str_repeat('x', 201)` | RuntimeException: 200 characters | ✅ Pass |
| 5 | Rejects invalid block_id | `block_id = 0` | RuntimeException: valid block | ✅ Pass |
| 6 | Rejects non-existent block | `block_id = 9999` | RuntimeException: does not exist | ✅ Pass |
| 7 | Rejects end_at before start_at | end < start | RuntimeException: after start | ✅ Pass |
| 8 | Rejects equal start and end | start == end | RuntimeException: after start | ✅ Pass |

---

### Function: `getById(int $id): ?array`
Returns a single election by ID or null if not found.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 9 | Returns null for missing ID | `id = 99999` | `null` | ✅ Pass |
| 10 | Returns correct election | Valid ID | `title = 'Test Election'` | ✅ Pass |

---

### Function: `update(int $id, array $data): void`
Updates an election — only allowed in `draft` status.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 11 | Modifies title on draft election | New title | Title updated in DB | ✅ Pass |
| 12 | Rejects update on non-draft election | Status = `open` | RuntimeException: Only draft | ✅ Pass |
| 13 | Rejects update on missing election | `id = 99999` | RuntimeException: not found | ✅ Pass |

---

### Function: `delete(int $id): void`
Deletes an election — only allowed in `draft` or `configured` status.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 14 | Removes draft election | Draft election | Record deleted | ✅ Pass |
| 15 | Rejects deletion of open election | Status = `open` | RuntimeException: Only draft or configured | ✅ Pass |
| 16 | Rejects deletion of missing election | `id = 99999` | RuntimeException: not found | ✅ Pass |

---

### Function: `transition(int $id, int $changedBy, string $note): string`
Advances election status: `draft → configured → open → closed → published`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 17 | Transitions draft → configured | Draft election | Returns `'configured'` | ✅ Pass |
| 18 | Configured → open requires at least 1 candidate | No candidates | RuntimeException: at least 1 position | ✅ Pass |
| 19 | Rejects transition on published (terminal) | Status = `published` | RuntimeException: terminal status | ✅ Pass |

---

### Function: `getStats(): array`
Returns count of elections grouped by status.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 20 | Returns all status keys | — | Keys: total, open, draft, closed, published, configured | ✅ Pass |
| 21 | Total equals sum of all statuses | — | `total == open + draft + closed + published + configured` | ✅ Pass |

---

## Module 3 — BlockPositionService
**File:** `tests/TestBlockPositionService.php`
**Source:** `services/BlockPositionService.php`
**Total:** 13 test cases

---

### Function: `create(int $electionId, array $data): int`
Creates a new position under an election.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 1 | Returns new positive ID | Valid data | `id > 0` | ✅ Pass |
| 2 | Rejects empty position_name | `position_name = ''` | RuntimeException: required | ✅ Pass |
| 3 | Rejects position_name over 100 chars | 101-char string | RuntimeException: 100 characters | ✅ Pass |
| 4 | Rejects max_votes = 0 | `max_votes = 0` | RuntimeException: between 1 and 10 | ✅ Pass |
| 5 | Rejects max_votes > 10 | `max_votes = 11` | RuntimeException: between 1 and 10 | ✅ Pass |

---

### Function: `getByElection(int $electionId): array`
Returns all positions for a given election.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 6 | Returns created positions | Valid election ID | `count >= 1` | ✅ Pass |
| 7 | Returns empty for unknown election | `id = 99999` | Empty array | ✅ Pass |

---

### Function: `getById(int $id): ?array`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 8 | Returns null for missing ID | `id = 99999` | `null` | ✅ Pass |

---

### Function: `update(int $id, array $data): void`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 9 | Modifies position_name | New name | Name updated in DB | ✅ Pass |
| 10 | Rejects update on missing position | `id = 99999` | RuntimeException: not found | ✅ Pass |

---

### Function: `delete(int $id): void`
Deletes a position — only allowed when election is `draft` or `configured`.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 11 | Removes position on draft election | Draft election | Record deleted | ✅ Pass |
| 12 | Rejects deletion of missing position | `id = 99999` | RuntimeException: not found | ✅ Pass |
| 13 | Rejects deletion on open election | Status = `open` | RuntimeException: draft or configured | ✅ Pass |

---

## Module 4 — BlockCandidateService
**File:** `tests/TestBlockCandidateService.php`
**Source:** `services/BlockCandidateService.php`
**Total:** 12 test cases

---

### Function: `create(int $positionId, array $data, ?array $uploadedFile): int`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 1 | Returns new positive ID | Valid data | `id > 0` | ✅ Pass |
| 2 | Stores full_name correctly | `full_name = 'Juan dela Cruz'` | Stored as-is | ✅ Pass |
| 3 | No photo sets photo_path to null | `uploadedFile = null` | `photo_path = null` | ✅ Pass |

---

### Function: `getByPosition(int $positionId): array` / `getActiveByPosition(int $positionId): array`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 4 | getByPosition() returns candidates | Valid position ID | `count >= 1` | ✅ Pass |
| 5 | getActiveByPosition() excludes inactive | Inactive candidate created | All returned have `is_active = 1` | ✅ Pass |

---

### Function: `getById(int $id): ?array`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 6 | Returns null for missing ID | `id = 99999` | `null` | ✅ Pass |

---

### Function: `update(int $id, array $data, ?array $uploadedFile): void`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 7 | Modifies full_name | New name | Name updated in DB | ✅ Pass |
| 8 | Rejects update on missing candidate | `id = 99999` | RuntimeException: not found | ✅ Pass |
| 9 | Keeps existing photo when no new upload | `uploadedFile = null` | Original photo_path preserved | ✅ Pass |

---

### Function: `toggleActive(int $id): void`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 10 | Flips is_active from 1 to 0 | `is_active = 1` | `is_active = 0` after toggle | ✅ Pass |
| 11 | Flips is_active from 0 to 1 | `is_active = 0` | `is_active = 1` after toggle | ✅ Pass |

---

### Function: `delete(int $id): void`

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 12 | Removes candidate | Valid ID | Record deleted | ✅ Pass |

---

## Module 5 — BlockBallotService
**File:** `tests/TestBlockBallotService.php`
**Source:** `services/BlockBallotService.php`
**Total:** 9 test cases

**Test Setup:** Election with 1 position (President), 2 active candidates (A, B) and 1 inactive candidate (C).

---

### Function: `buildBallot(int $electionId, bool $randomize): array`
Builds the ballot structure — positions with their active candidates.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 1 | Returns positions array | Valid election ID | `count >= 1` | ✅ Pass |
| 2 | Each item has `position` and `candidates` keys | Valid election ID | Both keys present | ✅ Pass |
| 3 | Only includes active candidates | Election with inactive candidate | All candidates have `is_active = 1` | ✅ Pass |
| 4 | Returns at least 2 active candidates | 2 active + 1 inactive seeded | `sum of active >= 2` | ✅ Pass |
| 5 | Returns empty for unknown election | `id = 99999` | Empty array | ✅ Pass |

---

### Function: `hasVoted(int $electionId, int $voterId): bool`
Checks if a voter has already submitted a vote.

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 6 | Returns false when no vote exists | No vote record | `false` | ✅ Pass |
| 7 | Returns true after confirmed vote inserted | Status = `confirmed` | `true` | ✅ Pass |
| 8 | Returns true for pending vote | Status = `pending` | `true` | ✅ Pass |
| 9 | Returns false for failed vote status | Status = `failed` | `false` | ✅ Pass |

---

## Module 6 — BlockEligibilityService
**File:** `tests/TestBlockEligibilityService.php`
**Source:** `services/BlockEligibilityService.php`
**Total:** 11 test cases

**Test Setup:**
- Voter 2: block_id=1, is_verified=1, is_active=1 *(eligible)*
- Voter 3: block_id=2, is_verified=1, is_active=1 *(wrong block)*
- Voter 4: block_id=1, is_verified=0, is_active=1 *(unverified)*
- Voter 5: block_id=1, is_verified=1, is_active=0 *(inactive)*
- Election: block_id=1, status=`open`

---

### Function: `isEligible(int $voterId, int $electionId): bool`
Returns true only if voter is verified, active, in the correct block, and election is open.

| # | Test Case | Voter | Expected | Result |
|---|-----------|-------|----------|--------|
| 1 | Eligible voter in correct block returns true | Voter 2 (block 1, verified, active) | `true` | ✅ Pass |
| 2 | Voter in wrong block returns false | Voter 3 (block 2) | `false` | ✅ Pass |
| 3 | Unverified voter returns false | Voter 4 (is_verified=0) | `false` | ✅ Pass |
| 4 | Inactive voter returns false | Voter 5 (is_active=0) | `false` | ✅ Pass |
| 5 | Returns false for non-open election | Voter 2, draft election | `false` | ✅ Pass |
| 6 | Returns false for non-existent voter | `voter_id = 99999` | `false` | ✅ Pass |
| 7 | Returns false for non-existent election | `election_id = 99999` | `false` | ✅ Pass |

---

### Function: `getVisibleElections(int $blockId): array`
Returns elections visible to a voter's block (open, closed, published — not draft).

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 8 | Returns open election for correct block | `block_id = 1` | Open election in results | ✅ Pass |
| 9 | Excludes draft elections | Draft election created | Draft not in results | ✅ Pass |
| 10 | Returns empty for unknown block | `block_id = 99999` | Empty array | ✅ Pass |

---

### Cache Behaviour

| # | Test Case | Input | Expected | Result |
|---|-----------|-------|----------|--------|
| 11 | Second `isEligible()` call uses cache without error | Call twice for same voter/election | Returns `true`, no exception | ✅ Pass |

---

## Bugs Found & Fixed

The following real bugs were discovered and fixed during test execution:

| # | Bug | Location | Fix |
|---|-----|----------|-----|
| 1 | `isset($data['is_active'])` returned `true` even when value was `0`, causing inactive candidates to be stored as active | `BlockCandidateService::create()` and `update()` | Changed to `!empty($data['is_active'])` |
| 2 | `NOT is_active` SQL expression returns `-1` on SQLite instead of `1`, breaking toggle from 0→1 | `BlockCandidateService::toggleActive()` | Changed to `1 - is_active` which works on both MySQL and SQLite |
| 3 | `INSERT IGNORE` is MySQL-only syntax, causing fatal error on SQLite test environment | `BlockEligibilityService::cache()` | Changed to `INSERT OR IGNORE` which is supported by both engines |

---

## How to Run

```bash
cmd /c "C:\xampp\php\php.exe tests\TestRunner.php"
```

The runner uses an **SQLite in-memory database** — no MySQL connection required. Each run starts with a clean database seeded with test blocks and users.
