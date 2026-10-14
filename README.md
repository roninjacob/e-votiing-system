# CICS Block Election System

This PHP app uses MySQL and the block-election schema in `database/mysql_schema.sql`.

## Setup with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. In phpMyAdmin, create a database named `evs_module2` using `utf8mb4_unicode_ci`, select it, then import `database/mysql_schema.sql`.
3. If you do not have a `.env`, copy `.env.example` to `.env`. Otherwise, add or update `EVS_DB_HOST`, `EVS_DB_PORT`, `EVS_DB_NAME`, `EVS_DB_USER`, and `EVS_DB_PASSWORD` in the existing file. The defaults target a local XAMPP MySQL installation with `root` and no password.
4. Run `php -S localhost:8000 -t public` and open `http://localhost:8000`.

The schema includes sample blocks, accounts, a block 1101 election, positions, and candidates. The sample election runs from October 1 to December 31, 2026. Its sample account passwords are documented in the schema seed comments; change them before using real student data.

Registration verifies the student's BatStateU email and assigns a block. Students can vote only in the election for their block. Candidate selections are stored without voter IDs; `block_votes` stores voter eligibility and receipt references separately. The imported schema includes OTP storage and anonymous selection tables required by the web app.

The Group 2 CICS-SC college-wide ballot is available at `cics-vote.php`, alongside the per-block ballot at `vote.php`. It admits active, verified voter accounts assigned to a CICS block and records at most one ballot per student. On an empty database it creates a clearly labelled sample college election with demo candidates; replace the demo roster, candidate credentials, platforms, photos, and dates before running a real election. CICS-wide candidate photos use `cics_candidates.photo_path`. New CICS election tables are created automatically for existing installations.

The student voting dashboard shows the election year and status, student year, semester, block, candidate count, and positions. Each candidate profile shows their name, position, party, credentials, platform, and photo. Candidate photos use `block_candidates.photo_path` and should point to a public image path such as `assets/img/candidates/name.jpg`. Enter real credentials in `block_candidates.credentials`; the dashboard displays “Not provided yet” when credentials, a photo, or platform have not been added. The app adds the credentials column to existing MySQL databases automatically.

The previous SQLite database file is left untouched; the app now connects to the configured MySQL database.

Manual database-to-dashboard test cases are documented in [`docs/test-cases.md`](docs/test-cases.md).
