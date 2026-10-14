<?php
declare(strict_types=1);

final class CicsVoting {
    private static function eligible(array $user): void {
        if (($user['role'] ?? '') !== 'voter' || empty($user['is_active']) || empty($user['is_verified']) || empty($user['block_id']))
            throw new ApiError('Only active, verified CICS students with an assigned block may vote.', null, 403);
    }

    private static function election(): array {
        $st = db()->query('SELECT * FROM cics_elections ORDER BY election_year DESC, id DESC LIMIT 1');
        $e = $st->fetch();
        if (!$e) throw new ApiError('There is no CICS-SC election configured yet.', null, 404);
        $now = time(); $start = strtotime($e['start_at']); $end = strtotime($e['end_at']);
        $e['display_status'] = $e['status'] === 'draft' || $e['status'] === 'configured' ? 'unavailable' :
            ($e['status'] !== 'open' || $now > $end ? 'closed' : ($now < $start ? 'upcoming' : 'open'));
        return $e;
    }

    private static function positions(int $electionId): array {
        $pdo = db();
        $st = $pdo->prepare('SELECT id, position_name AS name, max_votes, is_required FROM cics_positions WHERE election_id = ? ORDER BY sort_order,id');
        $st->execute([$electionId]); $positions = $st->fetchAll();
        $candidates = $pdo->prepare('SELECT id,full_name AS name,party,credentials,platform,photo_path FROM cics_candidates WHERE position_id=? AND is_active=1 ORDER BY full_name');
        foreach ($positions as &$p) {
            $candidates->execute([$p['id']]); $p['candidates'] = $candidates->fetchAll();
            $p['id'] = (int)$p['id']; $p['max_votes'] = (int)$p['max_votes']; $p['is_required'] = (bool)$p['is_required'];
        }
        unset($p);
        return $positions;
    }

    public static function ballot(array $user): array {
        self::eligible($user); $e = self::election(); $pdo = db();
        $st = $pdo->prepare('SELECT vote_ref FROM cics_votes WHERE election_id=? AND voter_id=? LIMIT 1');
        $st->execute([(int)$e['id'],(int)$user['id']]);
        $blockQuery = $pdo->prepare('SELECT block_name,section,year_level,semester,track FROM blocks WHERE id=?');
        $blockQuery->execute([(int)$user['block_id']]); $block = $blockQuery->fetch();
        $level = (int)($block['year_level'] ?? 0);
        $suffix = $level===1?'st':($level===2?'nd':($level===3?'rd':'th'));
        return ['name'=>$user['full_name'],'voted'=>$st->fetchColumn() ?: null,
            'election'=>['id'=>(int)$e['id'],'title'=>$e['title'],'description'=>$e['description'],
                'academic_year'=>(int)$e['election_year'],'year'=>$level ? $level.$suffix.' Year' : '',
                'block'=>$block ? $block['block_name'].' '.$block['section'] : '',
                'semester'=>(int)($block['semester'] ?? 0),'track'=>$block['track'] ?? null,
                'status'=>$e['display_status'],
                'window'=>date('M j, Y g:i A',strtotime($e['start_at'])).' – '.date('M j, Y g:i A',strtotime($e['end_at']))],
            'positions'=>self::positions((int)$e['id'])];
    }

    public static function submit(array $user, array $choices): string {
        self::eligible($user); $e=self::election();
        if ($e['display_status'] !== 'open') throw new ApiError('This CICS-SC election is not open for voting.',null,403);
        $positions=self::positions((int)$e['id']); $normalized=[];
        foreach ($positions as $p) {
            $raw=$choices[$p['id']] ?? $choices[(string)$p['id']] ?? null;
            if ($raw===null) $selected=[];
            elseif (is_array($raw)) {
                $selected=[]; foreach ($raw as $id) {
                    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) throw new ApiError('Invalid selection for '.$p['name'].'.');
                    $selected[]=(int)$id;
                }
                $selected=array_values(array_unique($selected));
            } elseif (is_int($raw) || (is_string($raw) && ctype_digit($raw))) $selected=[(int)$raw];
            else throw new ApiError('Invalid selection for '.$p['name'].'.');
            if (in_array(0,$selected,true) && count($selected)!==1) throw new ApiError('Choose either Abstain or candidates for '.$p['name'].'.');
            if ($p['is_required'] && !$selected) throw new ApiError('Choose a candidate or Abstain for '.$p['name'].'.');
            if (count($selected)>$p['max_votes']) throw new ApiError('Too many selections for '.$p['name'].'.');
            $valid=array_map('intval',array_column($p['candidates'],'id'));
            foreach ($selected as $id) if ($id!==0 && !in_array($id,$valid,true)) throw new ApiError('Invalid candidate for '.$p['name'].'.');
            $normalized[$p['id']]=$selected ?: [0];
        }
        $pdo=db(); $ref=strtoupper(bin2hex(random_bytes(8)));
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO cics_votes(election_id,voter_id,vote_ref) VALUES(?,?,?)')->execute([(int)$e['id'],(int)$user['id'],$ref]);
            $ins=$pdo->prepare('INSERT INTO cics_vote_selections(election_id,position_id,candidate_id) VALUES(?,?,?)');
            foreach ($normalized as $positionId=>$ids) foreach ($ids as $id) $ins->execute([(int)$e['id'],(int)$positionId,$id ?: null]);
            $pdo->commit();
        } catch (PDOException $x) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($x->getCode()==='23000') throw new ApiError('You have already voted in this CICS-SC election.',null,409);
            throw $x;
        }
        return $ref;
    }
}
