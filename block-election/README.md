# Block Election Module — Group 3

Per-block election system for the School E-Voting platform.
Students vote for officers of their own block only.

---

## Quick Start

### 1. Import the Database Schema

```bash
mysql -u <db_user> -p <db_name> < database/schema.sql
```

> Group 5 owns the MySQL server. Ask them for credentials and the database name.

---

### 2. Set Database Credentials

Edit `config/database.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'evoting_db');       // ← your DB name
define('DB_USER', 'evoting_user');     // ← your DB user
define('DB_PASS', 'REPLACE_WITH_DB_PASSWORD'); // ← your password
```

---

### 3. Set the Base URL

Edit `config/config.php`:

```php
define('BASE_URL', '/block-election'); // adjust if deployed elsewhere
```

If running at the web root, set it to `''`.

---

### 4. Configure Group 4 Integration

In `config/config.php`:

```php
define('GROUP4_VOTE_ENDPOINT', 'https://group4.internal/api/votes');
define('GROUP4_API_KEY',       'REPLACE_WITH_GROUP4_API_KEY');
define('GROUP4_SIMULATE',      true);  // set false when Group 4 is live
```

---

### 5. Open the App

Navigate to:

```
http://localhost/block-election/login.php
```

---

## Demo Accounts

| Role    | Student ID | Password    | Block  |
|---------|-----------|-------------|--------|
| Admin   | ADMIN001  | admin123    | —      |
| Adviser | ADV001    | adviser123  | BSIT 3A|
| Voter   | STU001    | student123  | BSIT 3A|
| Voter   | STU002    | student123  | BSIT 3A|
| Voter   | STU003    | student123  | BSIT 3B|

> **Note:** The seed data bcrypt hashes all map to the string `password` for simplicity.
> Regenerate real hashes before production:
> ```bash
> php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT, ['cost' => 12]);"
> ```

---

## Folder Structure

```
block-election/
├── config/
│   ├── config.php          # App constants, session bootstrap
│   └── database.php        # PDO singleton
├── includes/
│   ├── auth.php            # Guards, CSRF, flash, helpers
│   ├── header.php          # Navbar + Bootstrap CDN
│   └── footer.php          # Scripts + closing tags
├── services/
│   ├── BlockElectionService.php    # CRUD + status transitions
│   ├── BlockPositionService.php    # Position CRUD
│   ├── BlockCandidateService.php   # Candidate CRUD + photo upload
│   ├── BlockBallotService.php      # Ballot builder + has-voted check
│   ├── BlockEligibilityService.php # Block membership + eligibility cache
│   └── Group4Client.php            # HTTP bridge to Group 4 vote engine
├── admin/
│   ├── dashboard.php       # Stats + election list + audit log
│   ├── elections.php       # Election table with actions
│   ├── election_form.php   # Create/edit election form
│   ├── election_action.php # POST handler (create/update/delete/transition)
│   ├── positions.php       # Position CRUD + status control + history sidebar
│   ├── candidates.php      # Candidate cards + toggle/delete
│   └── candidate_form.php  # Add/edit candidate with photo upload
├── voter/
│   ├── elections.php       # Voter's election list with vote status
│   ├── ballot.php          # Randomized ballot with candidate cards
│   └── submit_vote.php     # Validates, calls Group 4, records vote_ref
├── assets/
│   ├── css/style.css       # Candidate card selection styles
│   └── js/app.js           # max_votes enforcement + confirm modal
├── uploads/candidates/     # Uploaded candidate photos (writable)
├── database/
│   └── schema.sql          # All tables + seed data
├── login.php               # DEV-ONLY stub (Group 1 replaces in production)
├── logout.php              # Session destroy
└── index.php               # Role-based redirect
```

---

## Status Flow

```
draft → configured → open → closed → published
```

- Only `draft` elections can be edited or deleted.
- Advancing to `open` requires at least 1 position with 1 active candidate.
- Every transition is logged in `block_election_status_logs`.

---

## Integration Points

| Group | What Group 3 does |
|-------|-------------------|
| Group 1 (Auth) | Reads `$_SESSION['user']` only. `login.php` is a dev stub. |
| Group 4 (Votes) | `Group4Client::submitVote()` POSTs JSON. Set `GROUP4_SIMULATE=true` offline. |
| Group 5 (DB) | Writes PDO prepared statements only. Never touches the server. |

---

## Security Notes

- CSRF token on every POST form.
- `session_regenerate_id(true)` on login.
- File uploads: finfo MIME check, jpg/png/webp only, 2 MB max, random filename.
- Voters can only see/vote in their own block's elections.
- `htmlspecialchars()` on all output via `e()` / `h()` helpers.
- No raw SQL — all queries use PDO prepared statements.

---

## Production Checklist

- [ ] Replace `login.php` with Group 1's real authentication
- [ ] Set `GROUP4_SIMULATE = false` and configure `GROUP4_VOTE_ENDPOINT` + `GROUP4_API_KEY`
- [ ] Set `APP_DEBUG = false` in `config/config.php`
- [ ] Make `uploads/candidates/` writable: `chmod 755 uploads/candidates/`
- [ ] Regenerate bcrypt password hashes for all seed accounts
- [ ] Set `BASE_URL` to match your deployment path
