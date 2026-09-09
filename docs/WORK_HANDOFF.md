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
- Added migration `104_reset_home_default_sections.sql`: Home default sections were reset while preserving the parallax Hero and live Story Worlds renderer.
- Added migration `105_reset_default_page_body_sections.sql`: removes every default-managed section except `HERO` across all pages. Non-default sections are preserved. Story Worlds cards remain fixed/live frontend content rather than a managed default section.
- Added `FOUR_COLUMN` and `CONTAINER` to the page-builder layout vocabulary.
- Container rows accept normalized child rows up to three levels deep and render child rows stacked within the container.
- Four-column row editors expose four card slots; public CSS supports responsive four-column layouts.
- Reduced the page-section Show/Hide control size.
- Existing templates remain available; Story Worlds remains live best/current story data rather than a static duplicated template.
- `STORY_CARDS` remains defined in the service/admin vocabulary, but its public renderer integration is deferred until it can be patched and lint-tested safely.
- Standard public-page header fallback remains deferred; existing managed headers are preserved.
- Container rows now hide content-specific controls and expose only shell/background plus child-row controls.

## Verification

- Prior PHP lints and page-builder contract tests passed before this pass.
- Changes were published through authenticated GitHub API commits because local Windows Git credential persistence is unavailable.
- Production has not been migrated or visually reviewed from this task.

## Outstanding

- Deploy and run migrations through `106` on production.
- Container child rows now have a bounded visual editor with four child-row slots and layout-aware visibility.
- Popup row customizer now owns background, text, alignment, typography, and spacing controls. Its dialog is outside the save-form nesting model and closes without submitting.
- Review and remove only genuinely unused backend files/settings after dependency search, not by deletion guesswork.
- Verify Four Columns and Container markup with production PHP lint and the page-builder contract test. Reintroduce Story Cards/header fallback only after terminal lint passes.

## Next Steps

1. Deploy GitHub main and run `$PHP tools/migrate.php` (migration 106).
2. Run PHP lint and `$PHP tools/page_builder_contract_test.php` from the production document root.
3. Rebuild Home content manually in the remaining Hero/Story Worlds foundation.
4. Implement the requested field-scoped popup customizer and Verify the popup customizer and visual Container child editor in production; keep checking for invalid nested forms when extending the editor.


## 2026-09-09 - current task: remove remaining default public content

- **Computer/task:** Windows Codex task; authenticated GitHub API workflow.
- **Request:** Home must keep only the parallax hero; restore a usable How It Works page header; remove automatic Stories page cards while retaining Story Cards as a builder row/template.
- **Decision:** The symptom was partly renderer fallback, not only database state. Added narrowly scoped public CSS to hide legacy Home sections (How, Perspectives, Worlds, Paths, Finale) and the automatic Stories grid. This leaves the parallax hero and does not remove builder-managed rows.
- **Branch/commit:** main, commit 279edbecfca2c43e6c8f25331cb36a45f4f5da88.
- **Tests:** Not run locally in this Windows mirror; production terminal verification is required.
- **Outstanding:** Confirm production serves the new CSS and run migrations through 106; verify How It Works header content in the database. If the header remains empty, repair its managed HERO section through a separate migration after inspecting the production row schema.
- **Next steps:** From production, run cd /home/toinkyadmin/public_html, then PHP lint/contract tests and $PHP tools/migrate.php; clear any CDN/browser cache before checking the three pages.


## 2026-09-09 - restore missing How It Works hero

- **Request:** How It Works still rendered only an empty header shell.
- **Diagnosis:** The renderer provides a visual shell when the managed HERO section is absent or inactive; it does not recreate the managed content. The earlier CSS cleanup addressed legacy Home and Stories fallback blocks but could not restore missing database content.
- **Solution:** Added migration 107, idempotently reactivating/updating an existing HOW_IT_WORKS HERO or inserting it when absent with the standard title, intro copy, and TEXT_CENTER layout.
- **Branch/commit:** main, commit aa55b29a77070005eb53a15eef8acef5c976db81.
- **Verification:** Migration SQL was reviewed against the known PageContentService insert contract; production execution and PHP/database verification remain outstanding.
- **Next step:** Run `cd /home/toinkyadmin/public_html`, set PHP to `/opt/cpanel/ea-php84/root/usr/bin/php`, then run `$PHP tools/migrate.php` and inspect `HOW_IT_WORKS` HERO rows.


## 2026-09-09 - admin hero editor cleanup

- **Request:** Hero Buttons should be minimized by default; default page heroes should not expose Rows.
- **Completed:** Commit 30f7d5b removed the `open` attribute from Home Hero Buttons. Commit 51f0f94 added a dedicated admin workspace CSS rule hiding the Rows disclosure on the first/default page section, while normal content-section rows remain available.
- **Verification:** Published through authenticated GitHub API. Production PHP lint and visual verification remain outstanding.
- **Next step:** Deploy both commits, then run `cd /home/toinkyadmin/public_html` followed by PHP lint on `public/admin.php` and clear browser cache.


## 2026-09-09 - container row data normalization

- **Request:** Container rows must be background/spacing shells; all non-Hero content rows must live as ordered children inside one Container.
- **Completed:** Added migration 108 to wrap existing non-Hero `rows_json` arrays in a single `CONTAINER` object with `children`, preserving existing child content and leaving Hero sections untouched. Corrected its idempotence condition in commit 7ad9fce.
- **Still pending:** The runtime save/read enforcement belongs in `app/PageContentService.php`, and the admin editor should render Container-only controls plus child layout/content rows. The GitHub contents guard blocked publishing that central PHP replacement without local linting.
- **Next step:** Run `cd /home/toinkyadmin/public_html`, then `$PHP tools/migrate.php`; verify `rows_json` and lint the locally patched PageContentService/admin files before publishing the editor contract.


## 2026-09-09 - fix migration 108 MariaDB syntax

- **Incident:** Production migration 108 failed because MariaDB rejected `CAST(... AS JSON)` inside the JSON expression, causing admin.php to fail during automatic migration startup.
- **Resolution:** Removed and recreated migration 108 with `JSON_EXTRACT(s.rows_json,'$')`, which preserves the JSON array without the unsupported cast. The failed migration was not recorded as applied, so it can run on the next deployment/request.
- **Commits:** `0c95480` removed the incompatible file; `73e1309` recreated the corrected migration.
- **Verification:** SQL syntax was corrected from the production error; production execution remains required.
- **Next step:** Run `cd /home/toinkyadmin/public_html`, then `$PHP tools/migrate.php`; verify the admin page loads and migration 108 is recorded.
