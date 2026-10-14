-- =============================================================================
-- Block Election Module â€” Schema & Seed Data (v2)
-- Section format: YSBB  (Y=year, S=semester, BB=block number e.g. 1101)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- â”€â”€â”€ Reference Tables â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

CREATE TABLE IF NOT EXISTS blocks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_name VARCHAR(100) NOT NULL,
    -- section format: YSBB â€” Y=year level, S=semester, BB=block number (e.g. 1101 = Year1 Sem1 Block01)
    section    VARCHAR(10)  NOT NULL,
    year_level TINYINT      NOT NULL,
    semester   TINYINT      NOT NULL DEFAULT 1,
    -- track: NULL for Year 1 & 2; 'BA' = Business Analytics, 'NT' = Networking for Year 3
    track      ENUM('BA','NT') NULL DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id    VARCHAR(50)  NOT NULL UNIQUE,
    full_name     VARCHAR(150) NOT NULL,
    -- Must be GSuite: YY-NNNNN@g.batstate-u.edu.ph
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','adviser','committee','voter') NOT NULL DEFAULT 'voter',
    block_id      INT UNSIGNED NULL,
    is_verified   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens (one active token per user)
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- â”€â”€â”€ Group 3 Tables â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

CREATE TABLE IF NOT EXISTS block_elections (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_id    INT UNSIGNED NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT         NULL,
    start_at    DATETIME     NOT NULL,
    end_at      DATETIME     NOT NULL,
    status      ENUM('draft','configured','open','closed','published') NOT NULL DEFAULT 'draft',
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (block_id)   REFERENCES blocks(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS block_positions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id    INT UNSIGNED NOT NULL,
    position_name  VARCHAR(100) NOT NULL,
    max_votes      TINYINT      NOT NULL DEFAULT 1,
    is_required    TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order     SMALLINT     NOT NULL DEFAULT 0,
    FOREIGN KEY (election_id) REFERENCES block_elections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS block_candidates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    position_id  INT UNSIGNED  NOT NULL,
    full_name    VARCHAR(150)  NOT NULL,
    party        VARCHAR(100)  NULL,
    credentials  TEXT          NULL,
    platform     TEXT          NULL,
    photo_path   VARCHAR(255)  NULL,
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    FOREIGN KEY (position_id) REFERENCES block_positions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group 2: college-wide CICS-SC election (separate from per-block elections).
CREATE TABLE IF NOT EXISTS cics_elections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    election_year YEAR NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    status ENUM('draft','configured','open','closed','published') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cics_positions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id INT UNSIGNED NOT NULL,
    position_name VARCHAR(100) NOT NULL,
    max_votes TINYINT NOT NULL DEFAULT 1,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    FOREIGN KEY (election_id) REFERENCES cics_elections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cics_candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    position_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    party VARCHAR(100) NULL,
    credentials TEXT NULL,
    platform TEXT NULL,
    photo_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (position_id) REFERENCES cics_positions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cics_votes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id INT UNSIGNED NOT NULL,
    voter_id INT UNSIGNED NOT NULL,
    vote_ref VARCHAR(100) NOT NULL UNIQUE,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cics_voter_election (voter_id,election_id),
    FOREIGN KEY (election_id) REFERENCES cics_elections(id) ON DELETE RESTRICT,
    FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cics_vote_selections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id INT UNSIGNED NOT NULL,
    position_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cics_result (election_id,position_id,candidate_id),
    FOREIGN KEY (election_id) REFERENCES cics_elections(id) ON DELETE RESTRICT,
    FOREIGN KEY (position_id) REFERENCES cics_positions(id) ON DELETE RESTRICT,
    FOREIGN KEY (candidate_id) REFERENCES cics_candidates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS block_election_status_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id  INT UNSIGNED NOT NULL,
    from_status  VARCHAR(20)  NOT NULL,
    to_status    VARCHAR(20)  NOT NULL,
    changed_by   INT UNSIGNED NULL,
    changed_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    note         TEXT         NULL,
    FOREIGN KEY (election_id) REFERENCES block_elections(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS block_eligibility_cache (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id INT UNSIGNED NOT NULL,
    voter_id    INT UNSIGNED NOT NULL,
    block_id    INT UNSIGNED NOT NULL,
    cached_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_voter_election (voter_id, election_id),
    FOREIGN KEY (election_id) REFERENCES block_elections(id) ON DELETE CASCADE,
    FOREIGN KEY (voter_id)    REFERENCES users(id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS block_votes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id  INT UNSIGNED NOT NULL,
    voter_id     INT UNSIGNED NOT NULL,
    block_id     INT UNSIGNED NOT NULL,
    vote_ref     VARCHAR(100) NOT NULL,
    payload_hash VARCHAR(64)  NOT NULL,
    group4_ref   VARCHAR(100) NULL,
    status       ENUM('pending','confirmed','failed') NOT NULL DEFAULT 'pending',
    submitted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_voter_election (voter_id, election_id),
    UNIQUE KEY uq_vote_ref (vote_ref),
    FOREIGN KEY (election_id) REFERENCES block_elections(id) ON DELETE RESTRICT,
    FOREIGN KEY (voter_id)    REFERENCES users(id)           ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web registration OTPs (users are created only after email verification).
CREATE TABLE IF NOT EXISTS web_otp_codes (
    otp_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(254) NOT NULL,
    purpose    VARCHAR(32) NOT NULL,
    code_hash  CHAR(64) NOT NULL,
    expires_at BIGINT NOT NULL,
    used_at    BIGINT NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at BIGINT NOT NULL,
    INDEX idx_web_otp_email_purpose (email, purpose, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stores ballot choices without a voter ID or receipt reference.
CREATE TABLE IF NOT EXISTS block_vote_selections (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id  INT UNSIGNED NOT NULL,
    position_id  INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vote_selection_result (election_id, position_id, candidate_id),
    CONSTRAINT fk_selection_election FOREIGN KEY (election_id) REFERENCES block_elections(id) ON DELETE RESTRICT,
    CONSTRAINT fk_selection_position FOREIGN KEY (position_id) REFERENCES block_positions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_selection_candidate FOREIGN KEY (candidate_id) REFERENCES block_candidates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA
-- Section format YSBB: 1=year, 1=semester, 01=block01
-- Passwords: admin123 / student123 (hashes generated by genhash.php)
-- =============================================================================

-- Blocks: section = YSBB, track NULL for Yr1/Yr2, BA/NT for Yr3
INSERT IGNORE INTO blocks (id, block_name, section, year_level, semester, track) VALUES
-- Year 1
(1,  'BSIT', '1101', 1, 1, NULL),  -- Year 1, Sem 1, Block 01
(2,  'BSIT', '1102', 1, 1, NULL),  -- Year 1, Sem 1, Block 02
(3,  'BSIT', '1201', 1, 2, NULL),  -- Year 1, Sem 2, Block 01
(4,  'BSIT', '1202', 1, 2, NULL),  -- Year 1, Sem 2, Block 02
-- Year 2
(5,  'BSIT', '2101', 2, 1, NULL),  -- Year 2, Sem 1, Block 01
(6,  'BSIT', '2102', 2, 1, NULL),  -- Year 2, Sem 1, Block 02
(7,  'BSIT', '2201', 2, 2, NULL),  -- Year 2, Sem 2, Block 01
(8,  'BSIT', '2202', 2, 2, NULL),  -- Year 2, Sem 2, Block 02
-- Year 3 â€” Business Analytics Track
(9,  'BSIT', '3101', 3, 1, 'BA'),  -- Year 3, Sem 1, BA Block 01
(10, 'BSIT', '3102', 3, 1, 'BA'),  -- Year 3, Sem 1, BA Block 02
(11, 'BSIT', '3201', 3, 2, 'BA'),  -- Year 3, Sem 2, BA Block 01
(12, 'BSIT', '3202', 3, 2, 'BA'),  -- Year 3, Sem 2, BA Block 02
-- Year 3 â€” Networking Track
(13, 'BSIT', '3103', 3, 1, 'NT'),  -- Year 3, Sem 1, NT Block 01
(14, 'BSIT', '3104', 3, 1, 'NT'),  -- Year 3, Sem 1, NT Block 02
(15, 'BSIT', '3203', 3, 2, 'NT'),  -- Year 3, Sem 2, NT Block 01
(16, 'BSIT', '3204', 3, 2, 'NT');  -- Year 3, Sem 2, NT Block 02

-- Admin (non-GSuite email allowed for admin/adviser roles)
INSERT IGNORE INTO users (id, student_id, full_name, email, password_hash, role, block_id, is_verified, is_active) VALUES
(1, 'ADMIN001', 'System Administrator', 'admin@cics.batstate-u.edu.ph',
 '$2y$10$soyaKwbGqlzy/Of2lMHeX.xfe.3gKKtuhnQhIsf4rhmGRLpa4WtCC', 'admin', NULL, 1, 1),
(2, 'ADV001', 'Maria Santos', 'adviser@cics.batstate-u.edu.ph',
 '$2y$10$soyaKwbGqlzy/Of2lMHeX.xfe.3gKKtuhnQhIsf4rhmGRLpa4WtCC', 'adviser', 1, 1, 1);

-- Students with GSuite emails: YY-NNNNN@g.batstate-u.edu.ph
INSERT IGNORE INTO users (id, student_id, full_name, email, password_hash, role, block_id, is_verified, is_active) VALUES
(3, '23-01041', 'Juan dela Cruz',  '23-01041@g.batstate-u.edu.ph',
 '$2y$10$OE95fmMWlFM8lCWk12E36.Epry2yCAjN767KWNY.o8iWOVhH3w9kq', 'voter', 1, 1, 1),
(4, '23-01042', 'Ana Reyes',       '23-01042@g.batstate-u.edu.ph',
 '$2y$10$OE95fmMWlFM8lCWk12E36.Epry2yCAjN767KWNY.o8iWOVhH3w9kq', 'voter', 1, 1, 1),
(5, '23-01043', 'Carlos Mendoza',  '23-01043@g.batstate-u.edu.ph',
 '$2y$10$OE95fmMWlFM8lCWk12E36.Epry2yCAjN767KWNY.o8iWOVhH3w9kq', 'voter', 4, 1, 1);

-- Open election for Block 1 (section 1101)
INSERT IGNORE INTO block_elections (id, block_id, title, description, start_at, end_at, status, created_by) VALUES
(1, 1, 'BSIT Block 1101 Officer Elections 2026',
 'Annual election for block officers of BSIT Section 1101.',
 '2026-10-01 08:00:00', '2026-12-31 23:59:59', 'open', 1);

INSERT IGNORE INTO block_election_status_logs (id, election_id, from_status, to_status, changed_by, note) VALUES
(1, 1, 'draft',      'configured', 1, 'Initial setup complete.'),
(2, 1, 'configured', 'open',       1, 'Election opened for voting.');

INSERT IGNORE INTO block_positions (id, election_id, position_name, max_votes, is_required, sort_order) VALUES
(1, 1, 'President',  1, 1, 1),
(2, 1, 'Secretary',  1, 1, 2),
(3, 1, 'Treasurer',  1, 1, 3);

INSERT IGNORE INTO block_candidates (id, position_id, full_name, party, credentials, platform, photo_path, is_active) VALUES
(1, 1, 'Sample Candidate A', 'Unity Party',    'Demo profile: replace with verified candidate credentials.', 'Stronger block unity and academic support.',    'assets/img/candidates/candidate-a.svg', 1),
(2, 1, 'Sample Candidate B', 'Progress Party', 'Demo profile: replace with verified candidate credentials.', 'Better facilities and student welfare.',        'assets/img/candidates/candidate-b.svg', 1),
(3, 2, 'Sample Candidate C', 'Unity Party',    'Demo profile: replace with verified candidate credentials.', 'Transparent communication and documentation.', 'assets/img/candidates/candidate-c.svg', 1),
(4, 2, 'Sample Candidate D', 'Progress Party', 'Demo profile: replace with verified candidate credentials.', 'Efficient record-keeping for all students.',    'assets/img/candidates/candidate-d.svg', 1),
(5, 3, 'Sample Candidate E', 'Unity Party',    'Demo profile: replace with verified candidate credentials.', 'Responsible fund management.',                  'assets/img/candidates/candidate-e.svg', 1),
(6, 3, 'Sample Candidate F', 'Progress Party', 'Demo profile: replace with verified candidate credentials.', 'Transparent budgeting and reporting.',          'assets/img/candidates/candidate-f.svg', 1);

SET FOREIGN_KEY_CHECKS = 1;

