# Toinky Shared Work Handoff

## Current Handoff

- **Date:** 2026-09-09
- **Task/computer:** Current Codex task on Windows checkout
- **Current request:** Extend Website Pages with new layouts, nested editable rows, visibility, reordering, deletion rules, and row-scoped editing.
- **Branch:** main
- **Commit:** Local follow-up commit pending after row typography verification.

## Completed

- Added migration `098_flexible_page_rows.sql` with `rows_json` and protected `is_default` section metadata.
- Added `TEXT_TOP`, `CARDS_ONLY`, and `TEXT_BULLETS` layout presets.
- Added row normalization, fallback rendering support, section hide/show, default deletion protection, and section reorder services.
- Added standard sub-row fields with add/remove controls to the admin section form.
- Added row-aware public rendering and CSS for shared-background child rows.
- Preserved legacy section images and three-column content when saved sections use fallback row data.
- Added compact row-scoped background and text color controls with server-side validation.
- Added `tools/page_builder_contract_test.php` and `docs/PAGE_BUILDER.md`.

## Verification

- PHP 8.4 test runtime: `app/PageContentService.php`, `public/admin.php`, `public/index.php`, and the contract test pass lint.
- `tools/page_builder_contract_test.php` passes.
- `git diff --check` passes.

## Outstanding

- Added draggable section cards, a Save Section Order form, and client-side order synchronization.
- Added compact row-level card slots with validated media selection, card titles, and card content.
- Added compact row-level buttons and links using the existing normalized button contract.
- Added row image selection with server-side media validation and section-image fallback.
- Added editable row-card icons with compact public rendering.
- Added non-destructive migrations and renderer fallbacks for the Home “How Toinky Works” and “Different Perspectives” sections.
- Added compact row typography and spacing controls with constrained server-side values.
- Richer per-row formatting, hover styling, and button color controls still need to be expanded.
- Story Worlds remains intentionally dynamic from live story data; Paths and Finale now have editable row-data fallbacks.
- The current page-builder changes are committed locally; remote publication is being verified in this task.

## Next Steps

1. Verify drag/drop section ordering UI and hide/delete controls against deployed data.
2. Add row-scoped hover styling and richer formatting controls using the existing standard editor helpers.
3. Seed Home defaults into editable rows and verify public output after migration.
4. Commit and push only when requested.

