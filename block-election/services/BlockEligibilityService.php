<?php
declare(strict_types=1);

/**
 * BlockEligibilityService
 *
 * Determines whether a voter is eligible to participate in a block election.
 * A voter is eligible when:
 *   1. Their block_id matches the election's block_id (block membership)
 *   2. Their account is verified and active (Group 1 guarantees this via session,
 *      but we double-check against the DB for defence-in-depth)
 *   3. The election is in 'open' status
 *
 * Results are cached in block_eligibility_cache to avoid repeated joins
 * on every ballot page load.
 */
class BlockEligibilityService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Returns true if the voter may vote in this election.
     * Populates the eligibility cache on first check.
     */
    public function isEligible(int $voterId, int $electionId): bool
    {
        // Check cache first
        $cached = $this->getCached($voterId, $electionId);
        if ($cached !== null) return $cached;

        $eligible = $this->computeEligibility($voterId, $electionId);

        // Only cache positive results — negative may change if admin fixes data
        if ($eligible) {
            $this->cache($voterId, $electionId);
        }

        return $eligible;
    }

    /** Return all open elections the voter is eligible for. */
    public function getEligibleElections(int $voterId, int $blockId): array
    {
        $stmt = $this->db->prepare(
            'SELECT be.*, b.block_name, b.section, b.year_level
             FROM block_elections be
             JOIN blocks b ON b.id = be.block_id
             WHERE be.block_id = ? AND be.status = \'open\'
             ORDER BY be.start_at ASC'
        );
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }

    /** All elections visible to a voter (open + published in their block). */
    public function getVisibleElections(int $blockId): array
    {
        $stmt = $this->db->prepare(
            'SELECT be.*, b.block_name, b.section, b.year_level
             FROM block_elections be
             JOIN blocks b ON b.id = be.block_id
             WHERE be.block_id = ? AND be.status IN (\'open\',\'closed\',\'published\')
             ORDER BY be.start_at DESC'
        );
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function computeEligibility(int $voterId, int $electionId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT u.block_id, u.is_verified, u.is_active, be.block_id AS election_block, be.status
             FROM users u
             JOIN block_elections be ON be.id = ?
             WHERE u.id = ?'
        );
        $stmt->execute([$electionId, $voterId]);
        $row = $stmt->fetch();

        if (!$row) return false;
        if (!$row['is_verified'] || !$row['is_active']) return false;
        if ($row['status'] !== 'open') return false;
        if ((int) $row['block_id'] !== (int) $row['election_block']) return false;

        return true;
    }

    private function getCached(int $voterId, int $electionId): ?bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM block_eligibility_cache
             WHERE voter_id = ? AND election_id = ?'
        );
        $stmt->execute([$voterId, $electionId]);
        $hit = $stmt->fetchColumn();
        return $hit !== false ? true : null; // null = not cached
    }

    private function cache(int $voterId, int $electionId): void
    {
        // Production uses MySQL/MariaDB; isolated tests use SQLite.
        $insert = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT OR IGNORE'
            : 'INSERT IGNORE';
        $stmt = $this->db->prepare(
            $insert . ' INTO block_eligibility_cache (election_id, voter_id, block_id)
             SELECT ?, ?, block_id FROM block_elections WHERE id = ?'
        );
        $stmt->execute([$electionId, $voterId, $electionId]);
    }
}
