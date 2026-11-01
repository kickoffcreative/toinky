<?php

// RecapChecks: deterministic checks over a player-view timeline and its
// rendered text, plus delivery/ending facts from the database. Each check
// returns one finding: ['check' => name, 'status' => pass|warning|fail,
// 'evidence' => quoted offending beat or '' ]. Run in Lab batches (and
// --recap CLI runs); any fail is appended to the run's warnings as
// "Recap check failed: ..." and invalidates the batch run, the same
// posture as today's integrity warnings (see
// StorySimulationBatchService::assessValidity).

final class RecapChecks
{
    // Movement language that, in a scene line AFTER the character's last
    // mechanic LOCATION move, suggests the prose moved the story without
    // a mechanic move — so the sidebar may disagree with the prose.
    // Warning only: transitions are authored prose, not mechanics.
    private const MOVE_WORDS = '/\b(walk|walks|walking|return|returns|returning|arrive|arrives|arriving|comes down|came down)\b/i';

    public static function run(int $sid, array $timeline, string $text, array $character): array
    {
        return [
            self::checkTokens($text),
            self::checkEmptyBeats($timeline),
            self::checkLabelArtifacts($sid, $timeline, $text),
            self::checkDelivery($sid),
            self::checkEndings($sid),
            self::checkSidebarDrift($sid, $timeline, $character),
        ];
    }

    public static function failures(array $findings): array
    {
        return array_values(array_filter($findings, static fn($f) => ($f['status'] ?? '') === 'fail'));
    }

    public static function warnings(array $findings): array
    {
        return array_values(array_filter($findings, static fn($f) => ($f['status'] ?? '') === 'warning'));
    }

    // Placeholder-token leaks: any literal [character]/[character1]/
    // [name]/[location]/[place] left in the rendered text.
    public static function checkTokens(string $text): array
    {
        $lines = explode("\n", $text);
        $hits = [];
        foreach ($lines as $line) {
            if (preg_match('/\[(character1?|name|location|place)\]/', $line)) $hits[] = trim($line);
            if (count($hits) >= 2) break;
        }
        if ($hits) return ['check' => 'placeholder tokens', 'status' => 'fail', 'evidence' => implode(' | ', $hits)];
        return ['check' => 'placeholder tokens', 'status' => 'pass', 'evidence' => ''];
    }

    // Empty beats: a public/personal entry with empty detail. A personal
    // DECISION blanked to its label (detail === '' after the builder's
    // label-blanking) is a warning ("blank beat"), not a fail.
    public static function checkEmptyBeats(array $timeline): array
    {
        $fails = [];
        $warns = [];
        foreach ($timeline as $second => $row) {
            foreach (($row['public'] ?? []) as $item) {
                if (trim((string)($item['detail'] ?? '')) === '') $fails[] = 'second ' . (int)$second . ' public beat (lead: ' . (string)($item['lead'] ?? '') . ') has empty detail';
            }
            foreach (($row['personal'] ?? []) as $item) {
                if (trim((string)($item['detail'] ?? '')) === '') {
                    $msg = 'second ' . (int)$second . ' personal beat "' . (string)($item['title'] ?? '') . '" has empty detail';
                    if (str_starts_with((string)($item['title'] ?? ''), 'You Chose to ')) $warns[] = $msg . ' (blank beat)';
                    else $fails[] = $msg;
                }
            }
        }
        if ($fails) return ['check' => 'empty beats', 'status' => 'fail', 'evidence' => $fails[0] . (count($fails) > 1 ? ' and ' . (count($fails) - 1) . ' more' : '')];
        if ($warns) return ['check' => 'empty beats', 'status' => 'warning', 'evidence' => $warns[0] . (count($warns) > 1 ? ' and ' . (count($warns) - 1) . ' more' : '')];
        return ['check' => 'empty beats', 'status' => 'pass', 'evidence' => ''];
    }

    // Label artifacts: a beat whose detail is an option label alone, a
    // public detail that still starts with its own lead name (the
    // builder strips that), or a rendered beat line containing literal
    // "Decision:" (a leaked history title). Beat lines only: endings are
    // authored prose and may legitimately use the word.
    public static function checkLabelArtifacts(int $sid, array $timeline, string $text): array
    {
        $labels = [];
        try {
            $q = db()->prepare('SELECT DISTINCT do.label FROM decision_options do JOIN decisions d ON d.decision_id=do.decision_id WHERE d.simulation_id=?');
            $q->execute([$sid]);
            foreach ($q->fetchAll() as $r) {
                $lab = mb_strtolower(trim((string)($r['label'] ?? '')));
                if ($lab !== '') $labels[$lab] = true;
            }
        } catch (Throwable $e) {}
        foreach ($timeline as $second => $row) {
            foreach (($row['public'] ?? []) as $item) {
                $detail = trim((string)($item['detail'] ?? ''));
                $lead = (string)($item['lead'] ?? '');
                if ($detail !== '' && isset($labels[mb_strtolower($detail)])) return ['check' => 'label artifacts', 'status' => 'fail', 'evidence' => 'second ' . (int)$second . ' public beat detail is an option label alone: "' . $detail . '"'];
                if ($lead !== '' && $detail !== '' && str_starts_with($detail, $lead . ' ')) return ['check' => 'label artifacts', 'status' => 'fail', 'evidence' => 'second ' . (int)$second . ' public beat detail still starts with its own lead name: "' . $detail . '"'];
            }
            foreach (($row['personal'] ?? []) as $item) {
                $detail = trim((string)($item['detail'] ?? ''));
                if ($detail !== '' && isset($labels[mb_strtolower($detail)])) return ['check' => 'label artifacts', 'status' => 'fail', 'evidence' => 'second ' . (int)$second . ' personal beat detail is an option label alone: "' . $detail . '"'];
            }
        }
        foreach (explode("\n", $text) as $line) {
            if (str_starts_with($line, '[') && str_contains($line, 'Decision:')) return ['check' => 'label artifacts', 'status' => 'fail', 'evidence' => 'rendered beat line contains literal "Decision:": "' . trim($line) . '"'];
        }
        return ['check' => 'label artifacts', 'status' => 'pass', 'evidence' => ''];
    }

    // Delivery counts, from the decisions table (not text): in a COMPLETE
    // game every playable character must have at least one delivered
    // (CHOSEN or NO_ACTION) decision — the Oct 31 defect signature was
    // zero decisions — and no decision may still be OPEN. Non-COMPLETE
    // sims are PARTIAL: warning, never fail.
    public static function checkDelivery(int $sid): array
    {
        $status = self::simStatus($sid);
        if ($status !== 'COMPLETE') return ['check' => 'delivery counts', 'status' => 'warning', 'evidence' => 'PARTIAL — simulation status is ' . ($status !== '' ? $status : 'unknown') . ', not COMPLETE'];
        $players = [];
        $open = [];
        try {
            $q = db()->prepare('SELECT character_id,name FROM characters WHERE simulation_id=? AND actor_type="PLAYER" ORDER BY slot_no,character_id');
            $q->execute([$sid]);
            $players = $q->fetchAll();
            $q = db()->prepare('SELECT d.decision_id,c.name,se.event_code FROM decisions d JOIN characters c ON c.character_id=d.character_id JOIN simulation_events se ON se.event_id=d.event_id WHERE d.simulation_id=? AND d.status="OPEN" ORDER BY d.decision_id LIMIT 5');
            $q->execute([$sid]);
            $open = $q->fetchAll();
        } catch (Throwable $e) {}
        foreach ($players as $p) {
            $n = 0;
            try {
                $q = db()->prepare('SELECT COUNT(*) FROM decisions WHERE character_id=? AND status IN ("CHOSEN","NO_ACTION")');
                $q->execute([(int)$p['character_id']]);
                $n = (int)$q->fetchColumn();
            } catch (Throwable $e) {}
            if ($n < 1) return ['check' => 'delivery counts', 'status' => 'fail', 'evidence' => 'playable character "' . (string)($p['name'] ?? '') . '" received no delivered decision'];
        }
        if ($open) {
            $first = $open[0];
            return ['check' => 'delivery counts', 'status' => 'fail', 'evidence' => count($open) . ' decision(s) still OPEN at COMPLETE (first: ' . (string)($first['name'] ?? '') . ' / ' . (string)($first['event_code'] ?? '') . ')'];
        }
        return ['check' => 'delivery counts', 'status' => 'pass', 'evidence' => ''];
    }

    // Endings present: at least one resolution fragment AND non-empty
    // FINAL_STATE text. Non-COMPLETE sims are PARTIAL: warning.
    public static function checkEndings(int $sid): array
    {
        $status = self::simStatus($sid);
        if ($status !== 'COMPLETE') return ['check' => 'endings present', 'status' => 'warning', 'evidence' => 'PARTIAL — simulation status is ' . ($status !== '' ? $status : 'unknown') . ', not COMPLETE'];
        $fragments = [];
        $finalText = '';
        try {
            if (class_exists('ResolutionService')) $fragments = ResolutionService::fragments($sid);
            $q = db()->prepare('SELECT event_text FROM simulation_events WHERE simulation_id=? AND event_code="FINAL_STATE" ORDER BY event_id DESC LIMIT 1');
            $q->execute([$sid]);
            $finalText = trim((string)($q->fetchColumn() ?: ''));
        } catch (Throwable $e) {}
        if (!$fragments) return ['check' => 'endings present', 'status' => 'fail', 'evidence' => 'no ending fragments rendered'];
        if ($finalText === '') return ['check' => 'endings present', 'status' => 'fail', 'evidence' => 'FINAL_STATE text is empty'];
        return ['check' => 'endings present', 'status' => 'pass', 'evidence' => ''];
    }

    // Sidebar-final-location heuristic, WARNING ONLY: strict equality is
    // tautological (move lines derive from the same LOCATION rows as the
    // sidebar), so instead flag scene lines with movement language at a
    // second after the character's last mechanic LOCATION move — prose
    // moving the story without a mechanic move. Needs at least one
    // mechanic move as a baseline; without one the check passes.
    public static function checkSidebarDrift(int $sid, array $timeline, array $character): array
    {
        $cid = (int)($character['character_id'] ?? 0);
        if ($cid < 1) return ['check' => 'sidebar final location', 'status' => 'pass', 'evidence' => ''];
        $lastLoc = null;
        try {
            $q = db()->prepare('SELECT MAX(event_second) FROM character_history WHERE character_id=? AND history_type="LOCATION"');
            $q->execute([$cid]);
            $v = $q->fetchColumn();
            $lastLoc = ($v === false || $v === null) ? null : (int)$v;
        } catch (Throwable $e) {}
        if ($lastLoc === null) return ['check' => 'sidebar final location', 'status' => 'pass', 'evidence' => 'no mechanic moves recorded; heuristic needs a move baseline'];
        // Epilogue prose: scene lines after the character's final decision
        // cannot mislead later play, so they do not flag. Only mid-story
        // moves (after the last mechanic move but before play ends) flag.
        $lastDecision = null;
        try {
            $dq = db()->prepare('SELECT MAX(opens_at_second) FROM decisions WHERE simulation_id=? AND character_id=?');
            $dq->execute([$sid, $cid]);
            $dv = $dq->fetchColumn();
            $lastDecision = ($dv === false || $dv === null) ? null : (int)$dv;
        } catch (Throwable $e) {}
        foreach ($timeline as $second => $row) {
            if ($lastDecision !== null && (int)$second > $lastDecision) continue;
            if ((int)$second <= $lastLoc) continue;
            foreach (($row['scene_lines'] ?? []) as $sceneLine) {
                if (preg_match(self::MOVE_WORDS, (string)$sceneLine)) {
                    return ['check' => 'sidebar final location', 'status' => 'warning', 'evidence' => 'story moves without a mechanic move — sidebar may disagree with prose: second ' . (int)$second . ': "' . trim((string)$sceneLine) . '"'];
                }
            }
        }
        return ['check' => 'sidebar final location', 'status' => 'pass', 'evidence' => ''];
    }

    private static function simStatus(int $sid): string
    {
        try {
            $q = db()->prepare('SELECT status FROM simulations WHERE simulation_id=?');
            $q->execute([$sid]);
            return (string)($q->fetchColumn() ?: '');
        } catch (Throwable $e) {
            return '';
        }
    }
}
