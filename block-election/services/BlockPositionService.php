<?php
declare(strict_types=1);

/**
 * BlockPositionService
 *
 * Manages positions within a block election.
 * Positions define what voters are choosing (President, Secretary, etc.)
 * and how many candidates they may select (max_votes).
 */
class BlockPositionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** All positions for an election, ordered by sort_order. */
    public function getByElection(int $electionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT bp.*,
                    (SELECT COUNT(*) FROM block_candidates bc
                     WHERE bc.position_id = bp.id AND bc.is_active = 1) AS active_candidates
             FROM block_positions bp
             WHERE bp.election_id = ?
             ORDER BY bp.sort_order ASC, bp.id ASC'
        );
        $stmt->execute([$electionId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM block_positions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $electionId, array $data): int
    {
        $this->validateData($data);

        $stmt = $this->db->prepare(
            'INSERT INTO block_positions
             (election_id, position_name, max_votes, is_required, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $electionId,
            $data['position_name'],
            (int) ($data['max_votes'] ?? 1),
            isset($data['is_required']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if (!$this->getById($id)) throw new RuntimeException('Position not found.');

        $this->validateData($data);

        $stmt = $this->db->prepare(
            'UPDATE block_positions
             SET position_name=?, max_votes=?, is_required=?, sort_order=?
             WHERE id=?'
        );
        $stmt->execute([
            $data['position_name'],
            (int) ($data['max_votes'] ?? 1),
            isset($data['is_required']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $position = $this->getById($id);
        if (!$position) throw new RuntimeException('Position not found.');

        // Guard: only allow deletion if election is draft or configured
        $stmt = $this->db->prepare('SELECT status FROM block_elections WHERE id = ? LIMIT 1');
        $stmt->execute([$position['election_id']]);
        $election = $stmt->fetch();
        if (!$election || !in_array($election['status'], ['draft', 'configured'], true)) {
            throw new RuntimeException('Positions can only be deleted while the election is in draft or configured status.');
        }

        $this->db->prepare('DELETE FROM block_positions WHERE id = ?')->execute([$id]);
    }

    // ── Private ───────────────────────────────────────────────────────────────────

    private function validateData(array $data): void
    {
        if (empty(trim($data['position_name'] ?? '')))
            throw new RuntimeException('Position name is required.');

        if (strlen(trim($data['position_name'])) > 100)
            throw new RuntimeException('Position name must not exceed 100 characters.');

        $maxVotes = (int) ($data['max_votes'] ?? 1);
        if ($maxVotes < 1 || $maxVotes > 10)
            throw new RuntimeException('Max votes must be between 1 and 10.');
    }
}
