<?php
declare(strict_types=1);

/**
 * BlockBallotService
 *
 * Builds the ballot for a given election:
 * - Admin preview: candidates in DB order
 * - Voter view: candidates shuffled per position to reduce position bias
 */
class BlockBallotService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Build ballot structure: array of positions, each with their candidates.
     * Candidates are shuffled when $randomize = true (voter view).
     *
     * @return array<int, array{position: array, candidates: array}>
     */
    public function buildBallot(int $electionId, bool $randomize = false): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM block_positions
             WHERE election_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$electionId]);
        $positions = $stmt->fetchAll();

        $ballot = [];
        foreach ($positions as $position) {
            $stmt2 = $this->db->prepare(
                'SELECT * FROM block_candidates
                 WHERE position_id = ? AND is_active = 1'
            );
            $stmt2->execute([$position['id']]);
            $candidates = $stmt2->fetchAll();

            if ($randomize) {
                shuffle($candidates);
            }

            $ballot[] = [
                'position'   => $position,
                'candidates' => $candidates,
            ];
        }

        return $ballot;
    }

    /**
     * Check whether a voter has already submitted a vote for this election.
     * We check our local block_votes table — Group 4 is the source of truth
     * for the actual vote content, but we track submission status here.
     */
    public function hasVoted(int $electionId, int $voterId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM block_votes
             WHERE election_id = ? AND voter_id = ? AND status IN (\'pending\',\'confirmed\')'
        );
        $stmt->execute([$electionId, $voterId]);
        return (bool) $stmt->fetchColumn();
    }
}
