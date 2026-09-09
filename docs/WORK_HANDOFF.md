# Toinky Shared Work Handoff

## Current Handoff

- **Date:** 2026-09-09
- **Task/computer:** Current Codex task on Windows checkout
- **Current request:** Remove two Home page-builder test rows titled “What Is Toinky”.
- **Branch:** main
- **Remote:** GitHub main
- **Latest remote commit:** aa99240 (remove Home page test rows)

## Completed

- Added flexible page rows, layout presets, shared-background child rows, visibility, default deletion protection, and drag/drop section ordering.
- Added compact row editors for text, bullets, images, cards, icons, buttons, links, colors, typography, and spacing.
- Preserved legacy section images and three-column content during the row transition.
- Added non-destructive Home migrations and renderer fallbacks for How Toinky Works, Perspectives, Paths, and Finale.
- Kept Story Worlds dynamic from live story data.
- Added `docs/PAGE_BUILDER.md` and `tools/page_builder_contract_test.php`.
- Added migration `102_remove_home_test_rows.sql`, which removes up to two Home rows only when an editable title, eyebrow, or label exactly equals `What Is Toinky`. Other rows and sections are unchanged.

## Verification

- Existing local PHP lint and `tools/page_builder_contract_test.php` passed before the cleanup migration.
- The cleanup migration was published to GitHub main as commit `aa99240`.
- Normal local Git push remains blocked by the Windows credential helper; authenticated GitHub API publication succeeded.

## Outstanding

- Deploy the latest main files to production.
- Run migrations and terminal checks on the server.
- Confirm the two rows are removed from Home after migration, then verify page-builder ordering and visibility.

## Next Steps

1. Deploy GitHub main, including migration `102_remove_home_test_rows.sql`.
2. From the server, run the migration and documented checks.
3. Review Home at desktop and mobile widths.
