# Toinky Backup & Restore Runbook

## What is backed up

- **Database:** the production MySQL/MariaDB database (99 tables, migration 132
  applied at the time of writing). This is the only copy of game history,
  review evidence, occasion ledger entries, and user accounts.
- **Files:** the deployed tree (`app/`, `public/`, `database/`, `tools/`, the
  20 root PHP wrappers) is recoverable from the repo (`kickoffcreative/toinky`,
  branch `main`) plus the deployed `.cpanel.yml`. The repo is NOT a backup of
  the database, and it is NOT a backup of `config/database.php` (server-owned,
  never committed) or `public/uploads/`.
- **Server-owned paths that must be backed up separately:** `config/database.php`,
  `.installed`, `public/uploads/`.

## Backup procedure

1. Take the database dump with `mysqldump` using the credentials in
   `config/database.php` on the live server (never commit those credentials).
   Record the dump file name, byte size, and the `schema_migrations` max
   migration at dump time.
2. Copy `config/database.php`, `.installed`, and `public/uploads/` to the same
   backup location.
3. Verify the dump loads: restore it to a disposable database and run the
   verification queries below. A backup that has never been restore-tested
   is not a backup.

## Restore procedure

1. Stop writes: put the site in maintenance (or confirm no game is RUNNING —
   a restore mid-game loses the in-flight tick).
2. Restore the database dump into the production database.
3. Restore `config/database.php`, `.installed`, and `public/uploads/`.
4. Deploy the matching repo commit (the deploy runs `tools/migrate.php` last,
   so migrations apply after code lands).
5. Run the verification queries below. Do not declare the restore complete
   until every check passes.

## Verification query set (run after every restore)

- `SELECT COUNT(*) FROM schema_migrations;` — expect the migration count for
  the restored dump (132 at the time of writing: migrations 001–132).
- `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();`
  — expect 99 live tables (the audit export: 99 tables; `story_factor_types`
  was dropped by migration 129 and must NOT reappear).
- Row counts for the large tables, compared against the pre-restore export:
  `simulations`, `games`, `characters`, `decisions`, `ai_reasoner_interactions`,
  `host_occasion_ledger`, `users`.
- Foreign-key check: every declared FK relationship must pass (the audit
  counted 137 declared FK relationships; the parser in `docs/SCHEMA.md`
  captures 135 — use the migrations as the full list).
- Spot check: the homepage loads, the dashboard loads, and a Lab simulation
  can be created for EMPTY_THRONE.

## Measured drill results

Record each drill here. Do NOT fabricate times — fill in only what was
actually measured.

| Date | Backup duration | Restore duration | Verification | Notes |
|------|----------------|------------------|--------------|-------|
| _not yet run_ | — | — | — | First drill still to be scheduled. |

## Retention

Task 17 rules (the authoritative copy is `tools/prune_ai_logs.php` and
migration 132; this section is a summary, not the implementation):

- `store_raw_payloads` is `'0'` going forward (migration 132, in
  `ai_reasoner_settings`). New AI interactions store `parsed_response_json`,
  metrics, and aggregates, but NOT the raw `request_json` / `response_json`.
- Completed AI logs older than 90 days: `request_json` and `response_json`
  are nulled out. `parsed_response_json` and all metric columns are kept.
- Unfinished AI logs (`request_completed_at_utc IS NULL`, started >24h ago):
  marked failed with an `error_text` note. They are NOT deleted outright
  until triaged.
- Rows are never deleted from `ai_reasoner_interactions`. `aiUsage()`
  per-simulation counts are derived live from this table, so the aggregates
  are preserved by construction.
- Expired/used `password_reset_tokens` and expired/accepted
  `user_invitations` / `game_invitations` are deleted by
  `tools/prune_expired_tokens.php` under the safe predicates
  (`expires_at_utc <= UTC_TIMESTAMP() OR used_at_utc IS NOT NULL` for
  password resets; `expires_at_utc <= UTC_TIMESTAMP() OR accepted_at_utc
  IS NOT NULL` for invitations). Both prune tools are CLI-only, `--dry-run`
  by default, and require `--apply` to change rows.
- Media 114/118/124 are removed only via `MediaLibraryService::delete()`
  after a live `usageByMediaIds()` re-check plus an inactive
  `plot_media_assets` check — never by raw SQL. See
  `tools/prune_expired_tokens.php`.
