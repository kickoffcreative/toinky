# Toinky Shared Work Handoff

## Current Handoff

- **Date:** 2026-09-09
- **Task/computer:** Current Codex task on Windows checkout
- **Current request:** Simplify Home page content and evolve the page builder around rows and containers.
- **Branch:** main
- **Remote:** GitHub main
- **Latest remote changes:** Home reset migration, current frontend snapshot, Four Columns and Container layout plumbing.

## Completed

- Added `docs/CURRENT_FRONTEND_CONTENT.md`, documenting the public Home, non-front-page, and member-facing content before the clean rebuild.
- Added migration `104_reset_home_default_sections.sql`: Home keeps only the parallax `HERO` and live `WORLDS` Story Worlds section; all other Home default sections are removed. Non-front-page page headers are not affected.
- Added `FOUR_COLUMN` and `CONTAINER` to the page-builder layout vocabulary.
- Container rows accept normalized child rows up to three levels deep and render child rows stacked within the container.
- Four-column row editors expose four card slots; public CSS supports responsive four-column layouts.
- Reduced the page-section Show/Hide control size.
- Existing templates remain available; Story Worlds remains live best/current story data rather than a static duplicated template.

## Verification

- Prior PHP lints and page-builder contract tests passed before this pass.
- Changes were published through authenticated GitHub API commits because local Windows Git credential persistence is unavailable.
- Production has not been migrated or visually reviewed from this task.

## Outstanding

- Deploy and run migrations through `104` on production.
- Replace the remaining editor label/behavior for any legacy “Sub-Rows” references with Container child-row editing.
- Build the requested field-scoped popup customizer; current controls remain inline.
- Review and remove only genuinely unused backend files/settings after dependency search, not by deletion guesswork.
- Verify Four Columns and Container markup with production PHP lint and the page-builder contract test.

## Next Steps

1. Deploy GitHub main and run `$PHP tools/migrate.php`.
2. Run PHP lint and `$PHP tools/page_builder_contract_test.php` from the production document root.
3. Rebuild Home content manually in the remaining Hero/Story Worlds foundation.
4. Implement the popup customizer and Container child-row editor as the next focused UI pass.
