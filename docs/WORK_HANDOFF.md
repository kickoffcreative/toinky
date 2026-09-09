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


## 2026-09-09 - Container shell editor correction

- **Clarification:** A Container is only a visual/layout shell. It must not expose generic title, body, bullets, buttons, or active content fields; its editable properties are layout/background/color/spacing plus ordered child content rows.
- **Completed:** Commit 57e0fc2 adds scoped admin workspace CSS that hides generic content controls only on rows containing the Container child editor. Child rows retain their layout/content controls; ordinary rows are unaffected.
- **Enforcement:** Commit 5b4fe38 adds MariaDB triggers for insert/update so non-Hero `rows_json` is wrapped in one Container with `children` at the database boundary.
- **Outstanding:** Replace the generic PHP row form with a semantically Container-specific form after local linting; CSS is the immediate safe UI correction. Production must run migrations 108 and 109.


## 2026-09-09 - remove leftover Home sections

- **Incident:** Production Home still showed two `What Is Toinky` sections. These were separate non-Hero sections, not Container rows, so Container-row styling could not affect them.
- **Resolution:** Added migration 110 to delete every non-Hero section belonging to the HOME page, leaving only the parallax HERO. This covers both duplicate test sections and any remaining default Home sections.
- **Commit:** `0ea7994`.
- **Next step:** Run `cd /home/toinkyadmin/public_html`, then `$PHP tools/migrate.php`; Home should show only Hero in Website Pages. Container styling applies only after creating normal non-Hero content sections.


## 2026-09-09 - empty Container child layout fix

- **Incident:** A saved Container with empty active child slots rendered blank placeholder sections publicly, producing the tiny black layout artifacts shown in the report. The admin Container also still exposed generic label/image controls.
- **Resolution:** Commit 7af53f6 scopes the admin stylesheet to hide Container label/content-image controls. Commit 63cd8b9 suppresses empty Container child sections in the public renderer using content-presence selectors; regular content rows remain unaffected.
- **Outstanding:** The clean architectural fix remains to make child-slot activity/content explicit in the PHP normalization and to add a true Container background-media property. These CSS mitigations address the observed output without replacing central PHP files.
