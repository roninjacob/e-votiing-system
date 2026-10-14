<?php
declare(strict_types=1);

/**
 * BlockElectionService
 *
 * Owns CRUD and status-transition logic for block_elections.
 * Status flow: draft → configured → open → closed → published
 */
class BlockElectionService
{
    private PDO $db;

    // Valid forward transitions only — no going back
    private const TRANSITIONS = [
        'draft'      => 'configured',
        'configured' => 'open',
        'open'       => 'closed',
        'closed'     => 'published',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Return all elections, optionally filtered by block. */
    public function getAll(?int $blockId = null): array
    {
        $sql = 'SELECT be.*, b.block_name, b.section, b.year_level,
                       u.full_name AS created_by_name
                FROM block_elections be
                LEFT JOIN blocks b ON b.id = be.block_id
                LEFT JOIN users  u ON u.id = be.created_by
                WHERE 1=1';
        $params = [];

        if ($blockId !== null) {
            $sql .= ' AND be.block_id = ?';
            $params[] = $blockId;
        }

        $sql .= ' ORDER BY be.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Return a single election by ID. */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT be.*, b.block_name, b.section, b.year_level
             FROM block_elections be
             LEFT JOIN blocks b ON b.id = be.block_id
             WHERE be.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Create a new election in draft status. */
    public function create(array $data, int $createdBy): int
    {
        $this->validateData($data);

        $stmt = $this->db->prepare(
            'INSERT INTO block_elections
             (block_id, title, description, start_at, end_at, status, created_by)
             VALUES (?, ?, ?, ?, ?, \'draft\', ?)'
        );
        $stmt->execute([
            $data['block_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['start_at'],
            $data['end_at'],
            $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update election — only allowed while status = 'draft'.
     * Throws RuntimeException if the election is not in draft.
     */
    public function update(int $id, array $data): void
    {
        $election = $this->getById($id);
        if (!$election) throw new RuntimeException('Election not found.');
        if ($election['status'] !== 'draft') {
            throw new RuntimeException('Only draft elections can be edited.');
        }

        $this->validateData($data);

        $stmt = $this->db->prepare(
            'UPDATE block_elections
             SET block_id=?, title=?, description=?, start_at=?, end_at=?
             WHERE id=?'
        );
        $stmt->execute([
            $data['block_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['start_at'],
            $data['end_at'],
            $id,
        ]);
    }

    /**
     * Delete election — only allowed while status = 'draft'.
     */
    public function delete(int $id): void
    {
        $election = $this->getById($id);
        if (!$election) throw new RuntimeException('Election not found.');
        if (!in_array($election['status'], ['draft', 'configured'], true)) {
            throw new RuntimeException('Only draft or configured elections can be deleted.');
        }
        $this->db->prepare('DELETE FROM block_elections WHERE id = ?')->execute([$id]);
    }

    /**
     * Advance election to the next status in the flow.
     * 'open' requires at least 1 position with at least 1 active candidate.
     */
    public function transition(int $id, int $changedBy, string $note = ''): string
    {
        $election = $this->getById($id);
        if (!$election) throw new RuntimeException('Election not found.');

        $current = $election['status'];
        if (!isset(self::TRANSITIONS[$current])) {
            throw new RuntimeException("Election is already in terminal status '{$current}'.");
        }

        $next = self::TRANSITIONS[$current];

        // Guard: opening requires at least 1 position with 1 active candidate
        if ($next === 'open') {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM block_positions bp
                 JOIN block_candidates bc ON bc.position_id = bp.id AND bc.is_active = 1
                 WHERE bp.election_id = ?'
            );
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() < 1) {
                throw new RuntimeException(
                    'Cannot open: election needs at least 1 position with 1 active candidate.'
                );
            }
        }

        $this->db->prepare(
            'UPDATE block_elections SET status = ? WHERE id = ?'
        )->execute([$next, $id]);

        // Audit every transition
        $this->db->prepare(
            'INSERT INTO block_election_status_logs
             (election_id, from_status, to_status, changed_by, note)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, $current, $next, $changedBy, $note]);

        return $next;
    }

    /** Return status history for an election. */
    public function getStatusLog(int $electionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, u.full_name AS changed_by_name
             FROM block_election_status_logs l
             LEFT JOIN users u ON u.id = l.changed_by
             WHERE l.election_id = ?
             ORDER BY l.changed_at DESC'
        );
        $stmt->execute([$electionId]);
        return $stmt->fetchAll();
    }

    /** Summary counts for the admin dashboard. */
    public function getStats(): array
    {
        $stmt = $this->db->query(
            'SELECT status, COUNT(*) AS cnt FROM block_elections GROUP BY status'
        );
        $rows = $stmt->fetchAll();
        $stats = ['total' => 0, 'open' => 0, 'draft' => 0, 'closed' => 0, 'published' => 0, 'configured' => 0];
        foreach ($rows as $r) {
            $stats[$r['status']] = (int) $r['cnt'];
            $stats['total'] += (int) $r['cnt'];
        }
        return $stats;
    }

    // ── Private ───────────────────────────────────────────────────────────────────

    private function validateData(array $data): void
    {
        if (empty($data['block_id']) || (int) $data['block_id'] <= 0)
            throw new RuntimeException('A valid block must be selected.');

        if (empty(trim($data['title'] ?? '')))
            throw new RuntimeException('Election title is required.');

        if (strlen(trim($data['title'])) > 200)
            throw new RuntimeException('Title must not exceed 200 characters.');

        if (empty($data['start_at']) || empty($data['end_at']))
            throw new RuntimeException('Start and end date/time are required.');

        $start = strtotime($data['start_at']);
        $end   = strtotime($data['end_at']);

        if (!$start || !$end)
            throw new RuntimeException('Invalid date/time format.');

        if ($end <= $start)
            throw new RuntimeException('End date/time must be after start date/time.');

        $stmt = $this->db->prepare('SELECT id FROM blocks WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $data['block_id']]);
        if (!$stmt->fetch())
            throw new RuntimeException('Selected block does not exist.');
    }
}
