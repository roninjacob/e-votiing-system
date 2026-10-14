<?php
declare(strict_types=1);

/**
 * Group4Client
 *
 * Thin HTTP bridge to Group 4's Vote Engine.
 * Group 3 NEVER stores vote content — only the reference IDs and a hash
 * of the payload for integrity verification.
 *
 * Set GROUP4_SIMULATE = true in config.php for offline development.
 */
class Group4Client
{
    /**
     * Submit a vote payload to Group 4.
     *
     * @param array $payload  { election_id, voter_id, block_id, selections: [...] }
     * @return array          { success: bool, vote_ref: string, group4_ref: string, message: string }
     */
    public function submitVote(array $payload): array
    {
        if (GROUP4_SIMULATE) {
            return $this->simulateSuccess('submitVote', $payload);
        }

        return $this->post(GROUP4_VOTE_ENDPOINT, $payload);
    }

    /**
     * Verify a previously submitted vote by its reference.
     *
     * @return array { success: bool, status: string, group4_ref: string }
     */
    public function verifyVote(string $voteRef): array
    {
        if (GROUP4_SIMULATE) {
            return ['success' => true, 'status' => 'confirmed', 'group4_ref' => 'SIM-' . $voteRef];
        }

        return $this->post(GROUP4_VOTE_ENDPOINT . '/verify', ['vote_ref' => $voteRef]);
    }

    /**
     * Fetch aggregated results for an election from Group 4.
     * Group 3 uses this only for display — tallying is Group 4's responsibility.
     *
     * @return array { success: bool, results: array }
     */
    public function fetchResults(int $electionId): array
    {
        if (GROUP4_SIMULATE) {
            return ['success' => true, 'results' => [], 'note' => 'Simulation mode — no real results.'];
        }

        return $this->post(GROUP4_VOTE_ENDPOINT . '/results', ['election_id' => $electionId]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /** Execute a JSON POST to Group 4 with auth headers and 10s timeout. */
    private function post(string $url, array $body): array
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-KEY: '  . GROUP4_API_KEY,
                'X-Source: group3-block-election',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Network error: ' . $error];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'message' => "Group 4 returned non-JSON (HTTP {$httpCode})."];
        }

        return $decoded;
    }

    /** Return a fake success response for offline development. */
    private function simulateSuccess(string $method, array $payload): array
    {
        return [
            'success'   => true,
            'vote_ref'  => 'SIM-' . strtoupper(bin2hex(random_bytes(8))),
            'group4_ref'=> 'G4SIM-' . time(),
            'message'   => "[SIMULATION] {$method} accepted.",
        ];
    }
}
