<?php
declare(strict_types=1);

/** Structural warnings only: does not infer facts from prose or change selection. */
final class EndingCoverageAudit
{
    public static function warnings(array $workspace): array
    {
        $active = static fn(array $row): bool => (int)($row['active'] ?? 1) === 1;
        $sections = [];
        foreach ($workspace['resolutions'] ?? [] as $fragment) {
            if ($active($fragment)) $sections[(string)$fragment['section_code']][] = $fragment;
        }
        $conditions = [];
        foreach ($workspace['resolution_conditions'] ?? [] as $condition) {
            if ($active($condition)) $conditions[(int)$condition['resolution_fragment_id']][] = $condition;
        }
        $definitions = [];
        foreach ($workspace['state_definitions'] ?? [] as $definition) {
            if ($active($definition)) $definitions[(string)$definition['target_code']][] = $definition;
        }

        $warnings = [];
        foreach ($sections as $section => $fragments) {
            // Match ResolutionService's first-match order, including priority ties.
            usort($fragments, static fn(array $a, array $b): int =>
                ((int)$a['priority'] <=> (int)$b['priority']) ?:
                ((int)$a['resolution_fragment_id'] <=> (int)$b['resolution_fragment_id']));
            $fallback = null;
            foreach ($fragments as $fragment) {
                $id = (int)$fragment['resolution_fragment_id'];
                if ($fallback !== null) {
                    $warnings[] = "Ending {$section} fragment #{$id} is hidden by earlier unconditional fragment #{$fallback}.";
                } elseif (empty($conditions[$id])) {
                    $fallback = $id;
                }
            }

            // A bounded, exact check for sections using one WORLD_STATE preset.
            // Skip other scopes, ambiguous codes and mixed/numeric/text conditions:
            // a structural audit cannot establish their reachable state space.
            $keys = [];
            $supported = true;
            foreach ($fragments as $fragment) {
                foreach ($conditions[(int)$fragment['resolution_fragment_id']] ?? [] as $condition) {
                    $keys[(string)$condition['state_key']] = true;
                    if (!in_array($condition['operator'], ['EQ', 'NEQ', 'IN', 'NOT_IN', 'EXISTS', 'NOT_EXISTS'], true)) $supported = false;
                }
            }
            if (!$supported || count($keys) !== 1) continue;
            $key = (string)array_key_first($keys);
            $matches = $definitions[$key] ?? [];
            if (count($matches) !== 1) continue;
            $definition = $matches[0];
            if ($definition['effect_scope'] !== 'WORLD_STATE' || $definition['value_type'] !== 'PRESET') continue;
            $values = array_values(array_unique(array_filter(array_map('trim',
                preg_split('/\r?\n/', (string)($definition['allowed_values_text'] ?? '')) ?: []),
                static fn(string $value): bool => $value !== '')));
            if (!$values || count($values) > 256) continue;

            $possible = $selected = [];
            $missing = [];
            foreach ($values as $value) {
                $winner = null;
                foreach ($fragments as $fragment) {
                    $id = (int)$fragment['resolution_fragment_id'];
                    $passes = true;
                    foreach ($conditions[$id] ?? [] as $condition) {
                        if (!self::passes($value, $condition)) { $passes = false; break; }
                    }
                    if (!$passes) continue;
                    $possible[$id] = true;
                    if ($winner === null) { $winner = $id; $selected[$id] = true; }
                }
                if ($winner === null) $missing[] = $value;
            }
            if ($missing) $warnings[] = "Ending {$section} has no matching fragment for configured {$key} preset(s): ".implode(', ', $missing).'.';
            // The unconditional pass above already explains later hidden rows.
            foreach ($fragments as $fragment) {
                $id = (int)$fragment['resolution_fragment_id'];
                if ($fallback !== null && self::after($id, $fallback, $fragments)) continue;
                if (!isset($possible[$id])) {
                    $warnings[] = "Ending {$section} fragment #{$id} matches no configured {$key} preset.";
                } elseif (!isset($selected[$id])) {
                    $warnings[] = "Ending {$section} fragment #{$id} is shadowed by earlier fragments for every configured {$key} preset it matches.";
                }
            }
        }
        return $warnings;
    }

    private static function after(int $id, int $fallback, array $fragments): bool
    {
        $seen = false;
        foreach ($fragments as $fragment) {
            $current = (int)$fragment['resolution_fragment_id'];
            if ($current === $id) return $seen;
            if ($current === $fallback) $seen = true;
        }
        return false;
    }

    private static function passes(string $value, array $condition): bool
    {
        $compare = (string)($condition['compare_value'] ?? '');
        $list = array_values(array_filter(array_map('trim', explode(',', $compare)), static fn(string $v): bool => $v !== ''));
        return match ($condition['operator']) {
            'EQ' => $value === $compare,
            'NEQ' => $value !== $compare,
            'IN' => in_array($value, $list, true),
            'NOT_IN' => !in_array($value, $list, true),
            'EXISTS' => true,
            'NOT_EXISTS' => false,
            default => false,
        };
    }
}
