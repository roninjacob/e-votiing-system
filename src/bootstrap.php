<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__));
const OTP_EXPIRY_MINUTES = 10, OTP_RESEND_SECONDS = 45, OTP_MAX_ATTEMPTS = 5;
const LOGIN_URL = 'login.php', HOME_URL = 'index.php';
const EMAIL_PATTERN = '/^[\w.+-]+@g\.batstate-u\.edu\.ph$/i';

class ApiError extends RuntimeException {
    public function __construct(string $m, public ?string $field = null, public int $status = 422) { parent::__construct($m); }
}

function env(string $key, ?string $default = null): ?string {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $f = APP_ROOT . '/.env';
        foreach (is_readable($f) ? file($f, FILE_IGNORE_NEW_LINES) : [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $vars[trim($k)] = trim(trim($v), "\"'");
        }
    }
    $v = $vars[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}
date_default_timezone_set(env('EVS_TIMEZONE', 'Asia/Manila'));

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('evs_session');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = env('EVS_DB_HOST', '127.0.0.1');
    $port = env('EVS_DB_PORT', '3306');
    $name = env('EVS_DB_NAME', 'evs_module2');
    $user = env('EVS_DB_USER', 'root');
    $pass = env('EVS_DB_PASSWORD', '');
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (PDOException $e) {
        throw new RuntimeException('Cannot connect to MySQL. Check EVS_DB_HOST, EVS_DB_PORT, EVS_DB_NAME, EVS_DB_USER and EVS_DB_PASSWORD in .env.', 0, $e);
    }
    $required = ['blocks', 'users', 'block_elections', 'block_positions', 'block_candidates', 'block_votes', 'web_otp_codes', 'block_vote_selections'];
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    foreach ($required as $table) {
        $check->execute([$table]);
        if (!(int)$check->fetchColumn()) throw new RuntimeException("MySQL database '$name' is missing table '$table'. Import database/mysql_schema.sql first.");
    }
    $columnCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'block_candidates' AND column_name = 'credentials'");
    $columnCheck->execute();
    if (!(int)$columnCheck->fetchColumn()) $pdo->exec('ALTER TABLE block_candidates ADD COLUMN credentials TEXT NULL AFTER party');
    $pdo->exec("CREATE TABLE IF NOT EXISTS cics_elections (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL,
        description TEXT NULL, election_year YEAR NOT NULL, start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL, status ENUM('draft','configured','open','closed','published') NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cics_positions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, election_id INT UNSIGNED NOT NULL,
        position_name VARCHAR(100) NOT NULL, max_votes TINYINT NOT NULL DEFAULT 1,
        is_required TINYINT(1) NOT NULL DEFAULT 1, sort_order SMALLINT NOT NULL DEFAULT 0,
        FOREIGN KEY (election_id) REFERENCES cics_elections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cics_candidates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, position_id INT UNSIGNED NOT NULL,
        full_name VARCHAR(150) NOT NULL, party VARCHAR(100) NULL, credentials TEXT NULL,
        platform TEXT NULL, photo_path VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (position_id) REFERENCES cics_positions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cics_votes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, election_id INT UNSIGNED NOT NULL,
        voter_id INT UNSIGNED NOT NULL, vote_ref VARCHAR(100) NOT NULL UNIQUE,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_cics_voter_election (voter_id,election_id),
        FOREIGN KEY (election_id) REFERENCES cics_elections(id) ON DELETE RESTRICT,
        FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cics_vote_selections (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, election_id INT UNSIGNED NOT NULL,
        position_id INT UNSIGNED NOT NULL, candidate_id INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cics_result (election_id,position_id,candidate_id),
        FOREIGN KEY (election_id) REFERENCES cics_elections(id) ON DELETE RESTRICT,
        FOREIGN KEY (position_id) REFERENCES cics_positions(id) ON DELETE RESTRICT,
        FOREIGN KEY (candidate_id) REFERENCES cics_candidates(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!(int)$pdo->query('SELECT COUNT(*) FROM cics_elections')->fetchColumn()) {
        $year = (int)date('Y');
        $pdo->prepare("INSERT INTO cics_elections(title,description,election_year,start_at,end_at,status) VALUES(?,?,?,?,?,'open')")
            ->execute(['CICS-SC College Election Sample', 'Demonstration election. Replace the sample candidate roster and election dates before a real election.', $year, "$year-01-01 00:00:00", "$year-12-31 23:59:59"]);
        $electionId = (int)$pdo->lastInsertId();
        $addPosition = $pdo->prepare('INSERT INTO cics_positions(election_id,position_name,max_votes,is_required,sort_order) VALUES(?,?,?,?,?)');
        $addCandidate = $pdo->prepare('INSERT INTO cics_candidates(position_id,full_name,party,credentials,platform,photo_path) VALUES(?,?,?,?,?,?)');
        foreach ([
            ['President', [['Sample Candidate A','candidate-a.svg'],['Sample Candidate B','candidate-b.svg']]],
            ['Vice President', [['Sample Candidate C','candidate-c.svg'],['Sample Candidate D','candidate-d.svg']]],
            ['Secretary', [['Sample Candidate E','candidate-e.svg'],['Sample Candidate F','candidate-f.svg']]]
        ] as $order => [$position, $names]) {
            $addPosition->execute([$electionId,$position,1,1,$order+1]); $positionId=(int)$pdo->lastInsertId();
            foreach ($names as $index => [$candidate,$photo]) $addCandidate->execute([$positionId,$candidate,'Sample Party '.($index ? 'B' : 'A'),'Demo profile: replace with verified candidate credentials.','Demo platform: replace with the candidate-approved statement.','assets/img/candidates/'.$photo]);
        }
    }
    $samplePhotos = [
        'Jose Rizal' => ['Sample Candidate A','candidate-a.svg'],
        'Andres Bonifacio' => ['Sample Candidate B','candidate-b.svg'],
        'Gabriela Silang' => ['Sample Candidate C','candidate-c.svg'],
        'Melchora Aquino' => ['Sample Candidate D','candidate-d.svg'],
        'Apolinario Mabini' => ['Sample Candidate E','candidate-e.svg'],
        'Juan Luna' => ['Sample Candidate F','candidate-f.svg']
    ];
    $updateSample = $pdo->prepare('UPDATE block_candidates SET full_name=?, credentials=COALESCE(credentials,?), photo_path=COALESCE(NULLIF(photo_path,\'\'),?) WHERE full_name=?');
    foreach ($samplePhotos as $oldName => [$newName,$photo]) $updateSample->execute([$newName,'Demo profile: replace with verified candidate credentials.','assets/img/candidates/'.$photo,$oldName]);
    $updateCicsPhoto = $pdo->prepare("UPDATE cics_candidates SET photo_path=COALESCE(NULLIF(photo_path,''),?), credentials=COALESCE(credentials,'Demo profile: replace with verified candidate credentials.') WHERE full_name=?");
    foreach (['A'=>'a','B'=>'b','C'=>'c','D'=>'d','E'=>'e','F'=>'f'] as $candidate => $letter) $updateCicsPhoto->execute(['assets/img/candidates/candidate-'.$letter.'.svg','Sample Candidate '.$candidate]);
    $missingBlocks = $pdo->query('SELECT b.id,b.block_name,b.section FROM blocks b LEFT JOIN block_elections e ON e.block_id=b.id WHERE e.id IS NULL')->fetchAll();
    if ($missingBlocks) {
        $year = (int)date('Y');
        $addElection = $pdo->prepare("INSERT INTO block_elections(block_id,title,description,start_at,end_at,status) VALUES(?,?,?, ?,?,'open')");
        $addBlockPosition = $pdo->prepare('INSERT INTO block_positions(election_id,position_name,max_votes,is_required,sort_order) VALUES(?,?,?,?,?)');
        $addBlockCandidate = $pdo->prepare('INSERT INTO block_candidates(position_id,full_name,party,credentials,platform,photo_path,is_active) VALUES(?,?,?,?,?,?,1)');
        $demoCandidates = [
            ['Sample Candidate A','Unity Party','candidate-a.svg','Stronger block unity and academic support.'],
            ['Sample Candidate B','Progress Party','candidate-b.svg','Better facilities and student welfare.'],
            ['Sample Candidate C','Unity Party','candidate-c.svg','Transparent communication and documentation.'],
            ['Sample Candidate D','Progress Party','candidate-d.svg','Efficient record-keeping for all students.'],
            ['Sample Candidate E','Unity Party','candidate-e.svg','Responsible fund management.'],
            ['Sample Candidate F','Progress Party','candidate-f.svg','Transparent budgeting and reporting.']
        ];
        foreach ($missingBlocks as $block) {
            $addElection->execute([(int)$block['id'],$block['block_name'].' Block '.$block['section'].' Demo Election '.$year,'Demonstration block election. Replace sample candidates and dates before a real election.',"$year-01-01 00:00:00","$year-12-31 23:59:59"]);
            $electionId=(int)$pdo->lastInsertId();
            foreach (['President'=>[0,1],'Vice President'=>[2,3],'Secretary'=>[4,5]] as $order=>$candidateIndexes) {
                $addBlockPosition->execute([$electionId,$order,1,1,array_search($order,['President','Vice President','Secretary'],true)+1]);
                $positionId=(int)$pdo->lastInsertId();
                foreach ($candidateIndexes as $index) {
                    [$candidate,$party,$photo,$platform]=$demoCandidates[$index];
                    $addBlockCandidate->execute([$positionId,$candidate,$party,'Demo profile: replace with verified candidate credentials.',$platform,'assets/img/candidates/'.$photo]);
                }
            }
        }
    }
    return $pdo;
}
