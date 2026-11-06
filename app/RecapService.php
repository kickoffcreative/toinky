<?php

// RecapService: the single player-view timeline builder shared by the live
// play page (public/play.php) and the Simulation Lab.
//
// The builders below moved verbatim from public/play.php lines 15-17
// (commit 37aa46b2, including the personal-column $worldDecisionText
// enrichment). The play page template still calls the global helper
// functions (story_moment, game_time, story_phase_label,
// player_visible_text, player_pov_narrative), so those functions remain
// defined in play.php; this service carries byte-identical private copies
// so Lab/CLI callers — where play.php never loads — render exactly what
// players see. tools/recap_service_guard_test.php proves the helper
// bodies match; do not edit one copy without the other.
//
// Design notes (Lab player-view refactor, Oct 31, 2026):
// - $snap is consumed as-is: snapshot world rows are visibility WORLD
//   only. Never re-query with different filters.
// - recap_transition is read from the LIVE plot_world_events table by
//   design (writers repair finished games' recaps without republishing),
//   exactly as the play page does.
// - Cast/factors/display values are copied from the live draft at
//   simulation creation even for pinned sims (SimulationService::create
//   lines 18-35). Rendered recaps therefore carry the character's
//   starting location/goal/fear in the header so draft drift is visible.
// - $playerView = true filters the three Lab-injected INTERACTION titles
//   ("Simulation Lab AI Control", "AI Options Considered",
//   "AI Reasoner Fallback"), which never exist in live human games. The
//   engine-real NPC title "Options Considered" (no "AI" prefix) is kept.

final class RecapService
{
    public const LAB_TITLES = ['Simulation Lab AI Control', 'AI Options Considered', 'AI Reasoner Fallback'];

    // Build the exact $timeline structure the play page template renders:
    // per story second: phase, public[], personal[], authored[], move,
    // scene_lines[]. $character is the snapshot character row (with
    // history) or null for a spectator view.
    public static function timeline(array $snap, ?array $character, int $storyDuration, bool $playerView = true): array
    {
        $sim = $snap['simulation'] ?? [];
        $sid = (int)($sim['simulation_id'] ?? 0);
        $plotId = (int)($sim['plot_id'] ?? 0);
        $charactersById = [];
        foreach (($snap['characters'] ?? []) as $c) $charactersById[(int)$c['character_id']] = $c;

        // Missed-option labels for NO_ACTION beats (play.php line 12).
        $missedOptionsByEvent = [];
        if ($character) {
            $mq = db()->prepare('SELECT d.event_id,do.label FROM decisions d JOIN decision_options do ON do.decision_id=d.decision_id WHERE d.character_id=? AND d.status="NO_ACTION" ORDER BY d.event_id,do.sort_order,do.decision_option_id');
            $mq->execute([$character['character_id']]);
            foreach ($mq->fetchAll() as $option) $missedOptionsByEvent[(int)$option['event_id']][] = (string)$option['label'];
        }

        // LOCATION history for move lines and [location]/[place] tokens.
        $locMoves = [];
        try {
            $lq = db()->prepare('SELECT character_id,event_second,detail_text FROM character_history WHERE simulation_id=? AND history_type=\'LOCATION\' ORDER BY event_second,history_id');
            $lq->execute([$sid]);
            foreach ($lq->fetchAll() as $lr) {
                if (preg_match('/^\s*(.*?)\s*→\s*(.*?)(?:\s*·.*)?$/u', (string)$lr['detail_text'], $lm) && trim($lm[2]) !== '') $locMoves[(int)$lr['character_id']][] = [(int)$lr['event_second'], trim($lm[2])];
            }
        } catch (Throwable $e) {}
        $locationAt = static function (array $ch, int $second) use ($locMoves): string {
            $loc = trim((string)($ch['initial_location'] ?? ($ch['current_location'] ?? '')));
            foreach ($locMoves[(int)$ch['character_id']] ?? [] as $mv) {
                if ($mv[0] < $second) $loc = $mv[1]; else break;
            }
            return $loc;
        };

        // Authored recap transitions (live draft table, by design).
        $recapTransitions = [];
        try {
            $rq = db()->prepare('SELECT event_code,recap_transition FROM plot_world_events WHERE plot_id=? AND recap_transition IS NOT NULL AND recap_transition<>""');
            $rq->execute([$plotId]);
            foreach ($rq->fetchAll() as $rr) {
                $rc = trim((string)($rr['recap_transition'] ?? ''));
                if ($rc !== '' && !isset($recapTransitions[(string)$rr['event_code']])) $recapTransitions[(string)$rr['event_code']] = $rc;
            }
        } catch (Throwable $e) {}

        // Shared timeline: everyone else's beats (play.php line 15).
        /* Decision outcomes are stamped with the second the choice was
           applied, which can be minutes after the beat that caused them.
           Key them to the causing event's second instead, so one article
           covers one beat whatever the click time was. World-event rows
           carry no cause, so they keep their own second. */
        $eventSecondById = [];
        foreach (($snap['world'] ?? []) as $w) {
            $eventSecondById[(int)($w['event_id'] ?? 0)] = (int)($w['event_second'] ?? 0);
        }
        $causeSecond = static function (int $rowSecond, int $causeId) use ($eventSecondById): int {
            if ($causeId > 0 && isset($eventSecondById[$causeId])) return $eventSecondById[$causeId];
            return $rowSecond;
        };
        $timeline = [];
        foreach ($snap['world'] as $w) {
            if (($w['event_code'] ?? '') === 'FINAL_STATE') continue;
            $actorId = (int)($w['actor_character_id'] ?? 0);
            if ($character && $actorId === (int)$character['character_id']) continue;
            $second = $causeSecond((int)$w['event_second'], (int)($w['caused_by_event_id'] ?? 0));
            $timeline[$second] ??= ['phase' => self::story_phase_for_second($second, $storyDuration), 'public' => [], 'personal' => [], 'seen' => [], 'authored' => [], 'move' => null];
            $rt = $recapTransitions[(string)($w['event_code'] ?? '')] ?? '';
            if ($rt !== '' && !in_array($rt, $timeline[$second]['authored'], true)) $timeline[$second]['authored'][] = $rt;
            $actor = $charactersById[$actorId] ?? null;
            $actorName = (string)($actor['name'] ?? '');
            $lead = $actorName !== '' ? $actorName : (string)$w['headline'];
            $detail = self::player_visible_text((string)$w['event_text']);
            if ($actorName !== '') {
                if (stripos((string)$w['headline'], 'opportunity pass') !== false) $detail = 'let an opportunity pass. The situation changed without their intervention.';
                elseif (trim($detail) !== '') $detail = self::observer_narrative($actor, $detail);
                else {
                    $tail = str_starts_with((string)$w['headline'], $actorName . ': ') ? trim(substr((string)$w['headline'], strlen($actorName) + 2)) : '';
                    $detail = $tail === '' ? '' : (stripos($tail, 'consequence of inaction') !== false ? $actorName . ' let the moment pass.' : $actorName . ' chose to ' . lcfirst(rtrim($tail, '.')) . '.');
                }
                if ($detail !== '' && str_starts_with($detail, $actorName . ' ')) $detail = substr($detail, strlen($actorName) + 1);
            }
            if ($character) $detail = self::player_pov_narrative($character, $detail);
            $isProse = false;
            if ($actorName !== '' && (str_contains($detail, '[character]') || str_contains($detail, '[character1]') || str_contains($detail, '[name]') || str_contains($detail, '[location]') || str_contains($detail, '[place]'))) {
                $detail = str_replace(['[character1]', '[character]', '[name]'], $actorName, $detail);
                $aloc = $locationAt($actor, $second);
                if ($aloc !== '') $detail = str_replace(['[location]', '[place]'], $aloc, $detail);
                $isProse = true;
                $lead = '';
            }
            $fingerprint = self::timeline_fingerprint($detail);
            if ($fingerprint !== '' && !isset($timeline[$second]['seen'][$fingerprint])) {
                /* One entry per actor per beat: a second outcome by the
                   same actor joins the first entry instead of opening
                   another article. Prose beats never merge. */
                $merged = false;
                if ($lead !== '' && !$isProse && !empty($timeline[$second]['public'])) {
                    foreach ($timeline[$second]['public'] as &$existing) {
                        if ((string)($existing['lead'] ?? '') === $lead && empty($existing['prose'])) {
                            $existing['detail'] = trim((string)$existing['detail'] . ' ' . $detail);
                            $merged = true;
                            break;
                        }
                    }
                    unset($existing);
                }
                if (!$merged) $timeline[$second]['public'][] = ['lead' => $lead, 'detail' => $detail, 'headline' => (string)$w['headline'], 'prose' => $isProse];
                $timeline[$second]['seen'][$fingerprint] = true;
            }
        }

        // Personal beats carry the same appended effect details as shared
        // beats: index decision-outcome world rows (result + details) by
        // actor, second, and option label (commit 37aa46b2).
        $worldDecisionText = [];
        foreach ($snap['world'] as $w) {
            $wa = (int)($w['actor_character_id'] ?? 0);
            $wn = (string)($charactersById[$wa]['name'] ?? '');
            $wh = (string)($w['headline'] ?? '');
            if ($wa > 0 && $wn !== '' && str_starts_with($wh, $wn . ': ')) $worldDecisionText[$wa . '_' . $causeSecond((int)($w['event_second'] ?? 0), (int)($w['caused_by_event_id'] ?? 0)) . '_' . trim(substr($wh, strlen($wn) + 2))] = (string)($w['event_text'] ?? '');
        }
        if ($character) foreach ($character['history'] as $h) {
            $type = (string)$h['history_type'];
            if ($type === 'STAT_CHANGE' || strcasecmp((string)$h['title'], 'Initial State') === 0) continue;
            // Player view only: Lab-injected INTERACTION rows never exist
            // in live human games, so they must not appear in player-view
            // recaps. The engine-real "Options Considered" title is kept.
            if ($playerView && $type === 'INTERACTION' && in_array((string)$h['title'], self::LAB_TITLES, true)) continue;
            $second = (int)$h['event_second'];
            if (($type === 'DECISION' || $type === 'NO_ACTION' || $type === 'LOCATION') && (int)($h['event_id'] ?? 0) > 0) $second = $causeSecond($second, (int)$h['event_id']);
            $timeline[$second] ??= ['phase' => self::story_phase_for_second($second, $storyDuration), 'public' => [], 'personal' => [], 'seen' => [], 'authored' => [], 'move' => null];
            $title = (string)$h['title'];
            $detail = self::player_pov_narrative($character, self::player_visible_text((string)$h['detail_text']));
            // If this decision's shared-timeline text adds effect details
            // to the result, show them in the personal beat too; otherwise
            // keep the stored result.
            if ($type === 'DECISION') {
                $wlabel = trim(preg_replace('/^Decision:\s*/i', '', (string)$h['title']));
                $wkey = (int)($character['character_id'] ?? 0) . '_' . (int)$second . '_' . $wlabel;
                if (isset($worldDecisionText[$wkey])) {
                    $wdetail = self::player_visible_text((string)$worldDecisionText[$wkey]);
                    $rdetail = self::player_visible_text((string)$h['detail_text']);
                    if ($wdetail !== '' && ($rdetail === '' || str_starts_with($wdetail, $rdetail))) $detail = self::player_pov_narrative($character, $wdetail);
                }
            }
            if ($type === 'LOCATION' && preg_match('/^\s*(.*?)\s*→\s*(.*?)(?:\s*·\s*(.*))?$/u', (string)$h['detail_text'], $lm) && trim($lm[2]) !== '') {
                $mreason = trim((string)($lm[3] ?? ''));
                if ($timeline[$second]['move'] === null) $timeline[$second]['move'] = ['from' => trim($lm[1]), 'to' => trim($lm[2]), 'reason' => $mreason];
                else {
                    $timeline[$second]['move']['to'] = trim($lm[2]);
                    if ($mreason !== '') $timeline[$second]['move']['reason'] = $mreason;
                }
                continue;
            }
            if ($type === 'DECISION') $title = 'You Chose to ' . preg_replace('/^Decision:\s*/i', '', $title);
            if ($type === 'DECISION' && trim($detail) === trim(preg_replace('/^Decision:\s*/i', '', (string)$h['title']))) $detail = '';
            elseif ($type === 'NO_ACTION') {
                $title = 'The Opportunity Passed';
                $options = $missedOptionsByEvent[(int)($h['event_id'] ?? 0)] ?? [];
                $detail = $options ? self::missed_opportunity_text($options) : 'You were given a chance to act, but the moment passed before you could act.';
            } elseif ($type === 'LOCATION' && preg_match('/^\s*(.*?)\s*→\s*(.*?)(?:\s*·.*)?$/u', $detail, $m)) $detail = 'You moved from ' . trim($m[1]) . ' to ' . trim($m[2]) . '.';
            if ($type === 'DECISION' && (str_contains($detail, '[character]') || str_contains($detail, '[character1]') || str_contains($detail, '[name]') || str_contains($detail, '[location]') || str_contains($detail, '[place]'))) {
                $detail = str_replace(['[character1]', '[character]', '[name]'], (string)($character['name'] ?? ''), $detail);
                $ploc = $locationAt($character, $second);
                if ($ploc !== '') $detail = str_replace(['[location]', '[place]'], $ploc, $detail);
            }
            $fingerprint = self::timeline_fingerprint($detail);
            if (($fingerprint === '' && $type === 'DECISION') || ($fingerprint !== '' && !isset($timeline[$second]['seen'][$fingerprint]))) {
                $timeline[$second]['personal'][] = ['label' => self::personal_experience_label($type), 'title' => $title, 'detail' => $detail];
                if ($fingerprint !== '') $timeline[$second]['seen'][$fingerprint] = true;
            }
        }

        // Prune empty seconds, sort, and attach scene lines (play.php
        // line 17): opening place line, authored transition preferred,
        // else reason-aware move line or templated arrival.
        foreach (array_keys($timeline) as $second) {
            unset($timeline[$second]['seen']);
            if (!$timeline[$second]['public'] && !$timeline[$second]['personal'] && empty($timeline[$second]['authored']) && empty($timeline[$second]['move'])) unset($timeline[$second]);
        }
        ksort($timeline, SORT_NUMERIC);
        $firstRow = true;
        foreach ($timeline as $second => &$row) {
            $sceneLines = [];
            if ($firstRow) {
                $firstRow = false;
                $place = trim((string)($character['initial_location'] ?? ($character['current_location'] ?? '')));
                if ($place !== '') $sceneLines[] = 'The story begins at ' . $place . '.';
            }
            if (!empty($row['authored'])) $sceneLines = array_merge($sceneLines, $row['authored']);
            elseif (!empty($row['move'])) {
                $mfrom = trim((string)$row['move']['from']);
                $mto = trim((string)$row['move']['to']);
                $mreason = trim((string)($row['move']['reason'] ?? ''));
                if ($mreason !== '' && $mreason !== 'Location changed' && $mreason !== 'Event movement') $sceneLines[] = (preg_match('/[.!?]$/u', $mreason) ? $mreason : $mreason . '.');
                elseif ($mto !== '') $sceneLines[] = $mfrom !== '' ? 'From ' . $mfrom . ', you arrive at ' . $mto . '.' : 'You arrive at ' . $mto . '.';
            }
            $row['scene_lines'] = $sceneLines;
        }
        unset($row);
        return $timeline;
    }

    // Plain-text rendering of one character's timeline, mirroring the
    // play page template (phase dividers, scene titles, scene lines,
    // public beats, personal beats) plus sidebar and endings blocks.
    // Timestamps are story seconds; compare beat order and content
    // against live games, never wall-clock timestamps literally.
    public static function renderText(array $timeline, array $character, int $storyDuration): string
    {
        $lines = [];
        $name = (string)($character['name'] ?? 'Unknown');
        $sid = (int)($character['simulation_id'] ?? 0);
        $storyTitle = '';
        $status = '';
        $versionText = 'unpinned draft';
        if ($sid > 0) {
            try {
                $q = db()->prepare('SELECT s.status,s.plot_version_id,p.title FROM simulations s JOIN plots p ON p.plot_id=s.plot_id WHERE s.simulation_id=?');
                $q->execute([$sid]);
                $row = $q->fetch();
                if ($row) {
                    $storyTitle = (string)($row['title'] ?? '');
                    $status = (string)($row['status'] ?? '');
                    $versionText = ((int)($row['plot_version_id'] ?? 0) > 0) ? ('content version ' . (int)$row['plot_version_id']) : 'unpinned draft';
                }
            } catch (Throwable $e) {}
        }
        $lines[] = 'PLAYER-VIEW RECAP — ' . $name;
        if ($storyTitle !== '') $lines[] = 'Story: ' . $storyTitle . ' · Content: ' . $versionText . ($status !== '' ? ' · Status: ' . $status : '');
        $lines[] = 'Timestamps are story seconds; phases mark story progress.';
        $side = self::sidebar($character);
        $startBits = [];
        if ($side['initial_location'] !== '') $startBits[] = 'starts at ' . $side['initial_location'];
        if ($side['initial_goal'] !== '') $startBits[] = 'goal: ' . $side['initial_goal'];
        if ($side['initial_fear'] !== '') $startBits[] = 'fear: ' . $side['initial_fear'];
        if ($startBits) $lines[] = 'At the start, ' . $name . ' ' . implode('; ', $startBits) . '.';

        $shownPhase = null;
        foreach ($timeline as $second => $row) {
            $phase = (string)($row['phase'] ?? '');
            if ($phase !== $shownPhase) {
                $shownPhase = $phase;
                $lines[] = '';
                $lines[] = '== ' . self::story_phase_label($phase) . ' ==';
            }
            $stamp = self::story_moment((int)$second, $storyDuration);
            foreach (($row['scene_lines'] ?? []) as $sceneLine) $lines[] = '[' . $stamp . '] ' . $sceneLine;
            if (!empty($row['public']) || !empty($row['personal'])) {
                // Scene titles above non-prose public beats, exactly as
                // the play page template renders them (per-second dedupe).
                $shownHeadline = null;
                foreach (($row['public'] ?? []) as $item) {
                    $itemHeadline = trim((string)($item['headline'] ?? ''));
                    $itemLead = (string)($item['lead'] ?? '');
                    if (empty($item['prose']) && $itemHeadline !== '' && $itemHeadline !== $itemLead && $itemHeadline !== $shownHeadline && stripos($itemHeadline, 'opportunity pass') === false) {
                        $shownHeadline = $itemHeadline;
                        $lines[] = '[' . $stamp . '] ' . $itemHeadline;
                    }
                    $detail = (string)($item['detail'] ?? '');
                    $lines[] = '[' . $stamp . '] ' . (!empty($item['prose']) ? $detail : trim($itemLead . ' ' . $detail));
                }
                foreach (($row['personal'] ?? []) as $item) {
                    $lines[] = '[' . $stamp . '] ' . (string)($item['label'] ?? '') . ' — ' . (string)($item['title'] ?? '') . ': ' . (string)($item['detail'] ?? '');
                }
            }
        }

        $lines[] = '';
        $lines[] = '-- FINAL STATE (sidebar) --';
        $lines[] = 'Location: ' . ($side['location'] !== '' ? $side['location'] : '(unknown)');
        $lines[] = 'Goal: ' . ($side['goal'] !== '' ? $side['goal'] : '(none)');
        $lines[] = 'Fear: ' . ($side['fear'] !== '' ? $side['fear'] : '(none)');
        $statBits = [];
        foreach ($side['stats'] as $code => $value) $statBits[] = $code . ' ' . $value;
        $lines[] = 'Stats: ' . ($statBits ? implode(', ', $statBits) : '(none)');

        $lines[] = '';
        $lines[] = '-- ENDINGS --';
        if ($status === 'COMPLETE' && $sid > 0) {
            $fragments = [];
            $epilogue = '';
            try {
                if (class_exists('ResolutionService')) ResolutionService::refresh($sid);
                if (class_exists('ResolutionService')) $fragments = ResolutionService::fragments($sid);
                $fq = db()->prepare('SELECT event_text FROM simulation_events WHERE simulation_id=? AND event_code="FINAL_STATE" ORDER BY event_id DESC LIMIT 1');
                $fq->execute([$sid]);
                $ftext = (string)($fq->fetchColumn() ?: '');
                if ($ftext !== '') $epilogue = self::player_pov_narrative($character, self::player_visible_text($ftext));
            } catch (Throwable $e) {}
            if ($fragments) {
                foreach ($fragments as $f) $lines[] = (string)($f['section_label'] ?? $f['section_code'] ?? '') . ': ' . (string)($f['text'] ?? '');
            } else {
                $lines[] = '(no ending fragments rendered)';
            }
            $lines[] = 'Epilogue: ' . ($epilogue !== '' ? $epilogue : '(no FINAL_STATE text)');
        } else {
            $lines[] = '(endings render when the story completes)';
        }
        return implode("\n", $lines) . "\n";
    }

    // Sidebar values for one character, for the final-location check.
    public static function sidebar(array $character): array
    {
        $stats = [];
        foreach (($character['stats'] ?? []) as $st) $stats[(string)($st['stat_code'] ?? '')] = (int)($st['current_value'] ?? 0);
        return [
            'name' => (string)($character['name'] ?? ''),
            'location' => trim((string)($character['current_location'] ?? '')),
            'goal' => trim((string)($character['goal_text'] ?? '')),
            'fear' => trim((string)($character['fear_text'] ?? '')),
            'stats' => $stats,
            'initial_location' => trim((string)($character['initial_location'] ?? '')),
            'initial_goal' => trim((string)($character['initial_goal_text'] ?? '')),
            'initial_fear' => trim((string)($character['initial_fear_text'] ?? '')),
        ];
    }

    // --- Helpers: byte-identical bodies to public/play.php lines 20-32.
    // The play page keeps the global copies for its template; this file
    // carries the private copies so Lab/CLI rendering never depends on
    // play.php loading. See tools/recap_service_guard_test.php.

    private static function game_time(int $seconds): string
    {
        if ($seconds >= 86400) {
            $days = intdiv($seconds, 86400);
            return $days . 'd ' . gmdate('H:i:s', $seconds % 86400);
        }
        if ($seconds >= 3600) return gmdate('G:i:s', $seconds);
        return gmdate('i:s', $seconds);
    }

    private static function ordinal_word(int $number): string
    {
        $words = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth', 6 => 'sixth', 7 => 'seventh'];
        return $words[$number] ?? ($number . (in_array($number % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$number % 10] ?? 'th')));
    }

    private static function story_moment(int $seconds, int $duration): string
    {
        if ($duration < 86400) return self::game_time($seconds);
        $seconds = min(max(0, $seconds), max(0, $duration - 1));
        $day = intdiv($seconds, 86400) + 1;
        $hour = (int)gmdate('G', $seconds % 86400);
        $part = $hour < 5 ? 'late at night' : ($hour < 12 ? 'sometime in the morning' : ($hour < 17 ? 'sometime in the afternoon' : ($hour < 22 ? 'sometime in the evening' : 'late at night')));
        return 'On the ' . self::ordinal_word($day) . ' day, ' . $part;
    }

    private static function story_phase_label(string $phase): string
    {
        return ['EXPOSITION' => 'The Story Begins', 'INCITING_INCIDENT' => 'Trouble Emerges', 'RISING_ACTION' => 'Pressure Builds', 'CLIMAX' => 'The Decisive Moment', 'RESOLUTION' => 'Consequences Settle', 'COMPLETE' => 'Consequences Settle'][$phase] ?? ucwords(strtolower(str_replace('_', ' ', $phase)));
    }

    private static function story_phase_for_second(int $second, int $duration): string
    {
        $ratio = $duration > 0 ? $second / $duration : 1;
        return $ratio < .18 ? 'EXPOSITION' : ($ratio < .42 ? 'INCITING_INCIDENT' : ($ratio < .72 ? 'RISING_ACTION' : ($ratio < .92 ? 'CLIMAX' : 'RESOLUTION')));
    }

    private static function personal_experience_label(string $type): string
    {
        return ['DECISION' => 'What You Did', 'NO_ACTION' => 'What You Left Undone', 'LOCATION' => 'Where You Went', 'KNOWLEDGE' => 'What You Learned', 'CONSEQUENCE' => 'What Changed for You', 'INTERACTION' => 'What You Considered', 'EXPERIENCED' => 'Your Point of View'][$type] ?? 'Your Point of View';
    }

    private static function timeline_fingerprint(string $text): string
    {
        $text = mb_strtolower(strip_tags($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim((string)$text);
    }

    private static function player_visible_text(string $text): string
    {
        $text = preg_replace('/\s*Final factors:\s*[^.!?]+[.!?]?/ui', '', $text);
        $text = preg_replace('/\s*(?:Location|Goal|Fear)\s*→\s*[^.!?]+[.!?]?/ui', '', $text);
        $text = preg_replace('/(^|[.!?]\s+|\n\s*)(?:ACHIEVED|FAILED|RESOLVED|ACTIVE|INACTIVE)(\s*[:·\-–—]\s*|\s*[.!?](?=\s|$))/u', '$1', $text);
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim((string)$text)) ?: [];
        $sentences = array_values(array_filter($sentences, static fn($sentence) => !preg_match('/\d+\s*→\s*\d+/u', $sentence) && !preg_match('/^[A-Z][A-Z0-9_]*\s*=\s*[^.!?]+[.!?]?$/u', trim($sentence)) && mb_strtolower(trim($sentence)) !== 'knowledge changed.'));
        return trim(implode(' ', $sentences));
    }

    private static function missed_opportunity_text(array $labels): string
    {
        $labels = array_values(array_filter(array_map(static fn($label) => lcfirst(rtrim(trim((string)$label), '.')), $labels)));
        if (!$labels) return 'You were given a chance to act, but the moment passed before you could act.';
        $last = array_pop($labels);
        $choices = $last ?? 'act';
        if ($labels) $choices = implode(', ', $labels) . (count($labels) > 1 ? ', or ' : ' or ') . $choices;
        return 'You were given the chance to ' . $choices . ', but the moment passed before you could act — the situation changed.';
    }

    private static function character_pronouns(array $character): array
    {
        $set = strtoupper((string)($character['pronoun_set'] ?? 'IT'));
        return $set === 'HE' ? ['he', 'him', 'his'] : ($set === 'SHE' ? ['she', 'her', 'her'] : ['it', 'it', 'its']);
    }

    // Authored prose renders verbatim on the play screen: perspective is
    // carried by the timeline lead, not by rewriting text. (Positional
    // verb-guessing here corrupted prose — "you alled", "finallied",
    // "shall we moved on".)
    private static function observer_narrative(array $actor, string $text): string
    {
        return $text;
    }

    private static function player_pov_narrative(array $character, string $text): string
    {
        return $text;
    }
}
