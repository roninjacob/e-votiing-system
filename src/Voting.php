<?php
declare(strict_types=1);

final class Voting {
    private static function assertEligible(array $user): void {
        if ($user['role'] !== 'voter' || empty($user['is_active']) || empty($user['is_verified']) || empty($user['block_id']))
            throw new ApiError('Only an active, verified voter assigned to a block can vote.', null, 403);
    }

    private static function election(array $user): array {
        $st = db()->prepare('SELECT e.*, b.block_name, b.section, b.year_level, b.semester, b.track
                             FROM block_elections e JOIN blocks b ON b.id = e.block_id
                             WHERE e.block_id = ? ORDER BY e.id DESC LIMIT 1');
        $st->execute([(int)$user['block_id']]);
        $election = $st->fetch();
        if (!$election) throw new ApiError('There is no election configured for your block yet.', null, 404);
        $now = time(); $start = strtotime($election['start_at']); $end = strtotime($election['end_at']);
        if (in_array($election['status'], ['draft', 'configured'], true)) $election['display_status'] = 'unavailable';
        elseif ($election['status'] !== 'open') $election['display_status'] = 'closed';
        elseif ($now < $start) $election['display_status'] = 'upcoming';
        elseif ($now > $end) $election['display_status'] = 'closed';
        else $election['display_status'] = 'open';
        $election['window'] = date('M j, Y g:i A', $start) . ' – ' . date('M j, Y g:i A', $end);
        return $election;
    }

    private static function positions(int $electionId): array {
        $pdo = db();
        $st = $pdo->prepare('SELECT id, position_name AS name, max_votes, is_required FROM block_positions WHERE election_id = ? ORDER BY sort_order, id');
        $st->execute([$electionId]); $positions = $st->fetchAll();
        $candidateQuery = $pdo->prepare('SELECT id, full_name AS name, party, credentials, platform, photo_path FROM block_candidates WHERE position_id = ? AND is_active = 1 ORDER BY full_name');
        foreach ($positions as &$position) {
            $candidateQuery->execute([$position['id']]);
            $position['candidates'] = $candidateQuery->fetchAll();
            $position['id'] = (int)$position['id'];
            $position['max_votes'] = (int)$position['max_votes'];
            $position['is_required'] = (bool)$position['is_required'];
        }
        unset($position);
        return $positions;
    }

    public static function ballot(array $user): array {
        self::assertEligible($user);
        $election = self::election($user); $pdo = db();
        $st = $pdo->prepare('SELECT vote_ref FROM block_votes WHERE election_id = ? AND voter_id = ? LIMIT 1');
        $st->execute([(int)$election['id'], (int)$user['id']]);
        $yearSuffix = ((int)$election['year_level'] === 1) ? 'st' : (((int)$election['year_level'] === 2) ? 'nd' : (((int)$election['year_level'] === 3) ? 'rd' : 'th'));
        return ['name' => $user['full_name'], 'voted' => $st->fetchColumn() ?: null,
                'election' => ['title' => $election['title'], 'status' => $election['display_status'], 'window' => $election['window'], 'academic_year' => date('Y', strtotime($election['start_at'])),
                               'block' => $election['block_name'] . ' ' . $election['section'], 'year' => $election['year_level'] . $yearSuffix . ' Year',
                               'semester' => $election['semester'], 'track' => $election['track']],
                'positions' => self::positions((int)$election['id'])];
    }

    /** @param array<int|string,mixed> $choices position id => candidate ID array (0 means abstain) */
    public static function submit(array $user, array $choices): string {
        self::assertEligible($user);
        $election = self::election($user);
        if ($election['display_status'] !== 'open') throw new ApiError($election['display_status'] === 'upcoming' ? 'Voting has not started yet.' : 'Voting is closed.', null, 403);
        $positions = self::positions((int)$election['id']); $normalized = [];
        foreach ($positions as $position) {
            $raw = $choices[$position['id']] ?? $choices[(string)$position['id']] ?? null;
            if ($raw === null) $selected = [];
            elseif (is_array($raw)) {
                $selected = [];
                foreach ($raw as $value) {
                    if (!is_int($value) && !(is_string($value) && ctype_digit($value))) throw new ApiError("Invalid selection for {$position['name']}.");
                    $selected[] = (int)$value;
                }
                $selected = array_values(array_unique($selected));
            }
            elseif (is_int($raw) || (is_string($raw) && ctype_digit($raw))) $selected = [(int)$raw];
            else throw new ApiError("Invalid selection for {$position['name']}.");
            if (in_array(0, $selected, true) && count($selected) !== 1) throw new ApiError("Choose either Abstain or candidates for {$position['name']}.");
            if ($position['is_required'] && !$selected) throw new ApiError("Choose a candidate or Abstain for {$position['name']}.");
            if (count($selected) > $position['max_votes']) throw new ApiError("Choose no more than {$position['max_votes']} candidate(s) for {$position['name']}.");
            $validIds = array_map('intval', array_column($position['candidates'], 'id'));
            foreach ($selected as $candidateId) if ($candidateId !== 0 && !in_array($candidateId, $validIds, true)) throw new ApiError("Invalid candidate for {$position['name']}.");
            $normalized[$position['id']] = $selected ?: [0];
        }

        $pdo = db(); $reference = strtoupper(bin2hex(random_bytes(8)));
        $payload = json_encode($normalized, JSON_THROW_ON_ERROR);
        $keyFile = APP_ROOT . '/data/.appkey';
        if (!is_file($keyFile)) { if (!is_dir(dirname($keyFile))) mkdir(dirname($keyFile), 0750, true); file_put_contents($keyFile, bin2hex(random_bytes(32))); }
        $payloadHash = hash_hmac('sha256', $reference . $payload, trim((string)file_get_contents($keyFile)));
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO block_votes (election_id, voter_id, block_id, vote_ref, payload_hash, status) VALUES (?,?,?,?,?,'confirmed')")
                ->execute([(int)$election['id'], (int)$user['id'], (int)$election['block_id'], $reference, $payloadHash]);
            $insertSelection = $pdo->prepare('INSERT INTO block_vote_selections (election_id, position_id, candidate_id) VALUES (?,?,?)');
            foreach ($normalized as $positionId => $candidateIds) {
                foreach ($candidateIds as $candidateId) $insertSelection->execute([(int)$election['id'], (int)$positionId, $candidateId ?: null]);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') throw new ApiError('You have already voted in this election.', null, 409);
            throw $e;
        }
        return $reference;
    }
}
